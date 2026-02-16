#!/usr/bin/env ruby
# Script PoC de raccordement au LDAP de l'Université de Lorraine
# Serveurs : ldaps://montet-dc1.ad.univ-lorraine.fr:636
#            ldaps://montet-dc2.ad.univ-lorraine.fr:636
#
# Le mot de passe doit être fourni via la variable d'environnement LDAP_PASSWORD
# Exemple : LDAP_PASSWORD=monmotdepasse LDAP_LOGIN=monlogin ruby ldap_ul.rb

require "net/ldap"

LDAP_SERVERS = %w[
  montet-dc1.ad.univ-lorraine.fr
  montet-dc2.ad.univ-lorraine.fr
].freeze

LDAP_PORT = 636
BASE_DN   = "OU=Personnels,OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr"

login    = ENV["LDAP_LOGIN"]
password = ENV["LDAP_PASSWORD"]

if login.nil? || login.empty? || password.nil? || password.empty?
  puts "Erreur : les variables d'environnement LDAP_LOGIN et LDAP_PASSWORD doivent être définies."
  puts "Exemple : LDAP_LOGIN=monlogin LDAP_PASSWORD=secret ruby ldap_ul.rb"
  exit 1
end

bind_dn = "#{login}@univ-lorraine.fr"

# --- Tentative de connexion sur les serveurs disponibles ---

ldap = nil
connected_server = nil

LDAP_SERVERS.each do |server|
  puts "Tentative de connexion à #{server}:#{LDAP_PORT} (LDAPS)..."

  ldap = Net::LDAP.new(
    host: server,
    port: LDAP_PORT,
    encryption: { method: :simple_tls },
    auth: {
      method: :simple,
      username: bind_dn,
      password: password
    }
  )

  if ldap.bind
    connected_server = server
    puts "[OK] Bind réussi sur #{server} en tant que #{bind_dn}"
    break
  else
    puts "[ERREUR] Bind échoué sur #{server} : #{ldap.get_operation_result.message}"
    ldap = nil
  end
end

unless ldap
  puts "\nImpossible de se connecter à aucun serveur LDAP."
  exit 1
end

# --- Recherche dans l'annuaire des personnels ---

puts "\n=== Recherche dans l'annuaire des personnels ===\n\n"

search_term = ARGV[0] || login
filter = Net::LDAP::Filter.eq("sAMAccountName", search_term)

ldap.search(base: BASE_DN, filter: filter, attributes: %w[cn mail memberOf sAMAccountName]) do |entry|
  puts "Nom          : #{entry[:cn].first}"
  puts "Login        : #{entry[:sAMAccountName].first}" if entry[:sAMAccountName].any?
  puts "Mail         : #{entry[:mail].first}" if entry[:mail].any?

  # Analyse du rattachement structurel
  if entry[:memberOf].any?
    puts "Rattachement :"
    entry[:memberOf].each do |group|
      if group.include?("GGA_STP_FHBAB")
        puts "  -> Département Informatique"
      elsif group.include?("GGA_STP_FHB")
        puts "  -> IUTNC"
      end
    end
  end
  puts
end

puts "Recherche terminée sur #{connected_server}."
