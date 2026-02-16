#!/usr/bin/env ruby
# Script de test de raccordement LDAP au serveur public forumsys
# Serveur : ldap.forumsys.com:389 (LDAP non sécurisé)

require "net/ldap"

LDAP_HOST = "ldap.forumsys.com"
LDAP_PORT = 389
BASE_DN   = "dc=example,dc=com"
BIND_DN   = "cn=read-only-admin,dc=example,dc=com"
BIND_PASS = "password"

# --- Connexion et bind ---

ldap = Net::LDAP.new(
  host: LDAP_HOST,
  port: LDAP_PORT,
  auth: {
    method: :simple,
    username: BIND_DN,
    password: BIND_PASS
  }
)

if ldap.bind
  puts "[OK] Bind réussi en tant que #{BIND_DN}"
else
  puts "[ERREUR] Bind échoué : #{ldap.get_operation_result.message}"
  exit 1
end

# --- Recherche de toutes les entrées ---

puts "\n=== Exploration de l'annuaire (Base DN : #{BASE_DN}) ===\n\n"

ldap.search(base: BASE_DN, filter: Net::LDAP::Filter.eq("objectClass", "*")) do |entry|
  puts "DN: #{entry.dn}"
  entry.each do |attr, values|
    values.each { |v| puts "  #{attr}: #{v}" }
  end
  puts "-" * 40
end

# --- Recherche des utilisateurs (personnes) ---

puts "\n=== Liste des utilisateurs (objectClass=person) ===\n\n"

user_filter = Net::LDAP::Filter.eq("objectClass", "person")

ldap.search(base: BASE_DN, filter: user_filter, attributes: %w[uid cn mail telephoneNumber]) do |entry|
  puts "Utilisateur : #{entry[:cn].first}"
  puts "  UID   : #{entry[:uid].first}" if entry[:uid].any?
  puts "  Mail  : #{entry[:mail].first}" if entry[:mail].any?
  puts "  Tél.  : #{entry[:telephoneNumber].first}" if entry[:telephoneNumber].any?
  puts
end

# --- Test d'authentification avec un utilisateur standard ---

puts "=== Test d'authentification utilisateur (Einstein) ===\n\n"

user_dn   = "uid=einstein,dc=example,dc=com"
user_pass  = "password"

user_ldap = Net::LDAP.new(
  host: LDAP_HOST,
  port: LDAP_PORT,
  auth: {
    method: :simple,
    username: user_dn,
    password: user_pass
  }
)

if user_ldap.bind
  puts "[OK] Authentification réussie pour #{user_dn}"
else
  puts "[ERREUR] Authentification échouée pour #{user_dn} : #{user_ldap.get_operation_result.message}"
end
