#!/usr/bin/env ruby
# PoC de connexion au LDAP de l'Université de Lorraine
# Utilisation : LDAP_LOGIN=monlogin LDAP_PASSWORD=monmdp ruby ldap_ul.rb

require "net/ldap"

login = ENV["LDAP_LOGIN"]
password = ENV["LDAP_PASSWORD"]

if login.nil? || login.empty? || password.nil? || password.empty?
  puts "Il faut définir LDAP_LOGIN et LDAP_PASSWORD"
  puts "Exemple : LDAP_LOGIN=monlogin LDAP_PASSWORD=monmdp ruby ldap_ul.rb"
  exit 1
end

# les 2 serveurs dispo (on essaie le premier, puis le second si ça marche pas)
serveurs = [
  "montet-dc1.ad.univ-lorraine.fr",
  "montet-dc2.ad.univ-lorraine.fr"
]

# base DN pour les étudiants (la branche Personnels ne contient pas les comptes étudiants)
# si on ne trouve rien, on tente une recherche plus large pour trouver le bon Base DN
base_dn_etudiants = "OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr"
base_dn_personnels = "OU=Personnels,OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr"

ldap = nil
serveur_ok = nil

serveurs.each do |srv|
  puts "Connexion à #{srv}..."

  ldap = Net::LDAP.new(
    host: srv,
    port: 636,
    encryption: { method: :simple_tls },
    auth: {
      method: :simple,
      username: "#{login}@etu.univ-lorraine.fr",
      password: password
    }
  )

  if ldap.bind
    serveur_ok = srv
    puts "Connecté à #{srv}"
    break
  else
    puts "Raté sur #{srv} : #{ldap.get_operation_result.message}"
    ldap = nil
  end
end

if ldap.nil?
  puts "Impossible de se connecter aux serveurs LDAP."
  exit 1
end

# recherche d'un utilisateur (par défaut soi-même, sinon on passe un login en argument)
qui = ARGV[0] || login
filtre = Net::LDAP::Filter.eq("sAMAccountName", qui)

puts "\nRecherche de '#{qui}' dans l'annuaire...\n\n"

# on cherche d'abord dans la branche étudiants (plus large), puis personnels si rien trouvé
trouve = false

[base_dn_etudiants, base_dn_personnels].each do |base|
  ldap.search(base: base, filter: filtre, attributes: ["cn", "mail", "memberOf", "sAMAccountName", "distinguishedName"]) do |entry|
    trouve = true
    puts "DN    : #{entry[:distinguishedName].first}" if entry[:distinguishedName].any?
    puts "Nom   : #{entry[:cn].first}"
    puts "Login : #{entry[:sAMAccountName].first}" if entry[:sAMAccountName].any?
    puts "Mail  : #{entry[:mail].first}" if entry[:mail].any?

    # on regarde les groupes pour trouver le rattachement
    if entry[:memberOf].any?
      entry[:memberOf].each do |groupe|
        if groupe.include?("GGA_STP_FHBAB")
          puts "Rattachement : Département Informatique"
        elsif groupe.include?("GGA_STP_FHB")
          puts "Rattachement : IUTNC"
        end
      end
    end
    puts ""
  end
  break if trouve
end

if !trouve
  puts "Aucun résultat pour '#{qui}'."
end

puts "Recherche terminée (serveur : #{serveur_ok})"
