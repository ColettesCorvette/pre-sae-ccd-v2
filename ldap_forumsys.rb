#!/usr/bin/env ruby
# Test de raccordement LDAP sur le serveur public forumsys
# ldap.forumsys.com, port 389

require "net/ldap"

# config du serveur de test
host = "ldap.forumsys.com"
port = 389
base = "dc=example,dc=com"

# on se connecte avec le compte admin en lecture seule
ldap = Net::LDAP.new(
  host: host,
  port: port,
  auth: {
    method: :simple,
    username: "cn=read-only-admin,dc=example,dc=com",
    password: "password"
  }
)

if ldap.bind
  puts "Bind OK avec le compte admin"
else
  puts "Erreur de bind : #{ldap.get_operation_result.message}"
  exit 1
end

# on parcourt tout l'annuaire pour voir ce qu'il y a dedans
puts "\n--- Contenu de l'annuaire ---\n\n"

ldap.search(base: base, filter: Net::LDAP::Filter.eq("objectClass", "*")) do |entry|
  puts "DN: #{entry.dn}"
  entry.each do |attr, values|
    values.each { |v| puts "  #{attr}: #{v}" }
  end
  puts ""
end

# on filtre pour n'avoir que les utilisateurs
puts "--- Utilisateurs (objectClass=person) ---\n\n"

filtre_users = Net::LDAP::Filter.eq("objectClass", "person")

ldap.search(base: base, filter: filtre_users, attributes: ["uid", "cn", "mail", "telephoneNumber"]) do |entry|
  puts "#{entry[:cn].first}"
  puts "  uid  : #{entry[:uid].first}" if entry[:uid].any?
  puts "  mail : #{entry[:mail].first}" if entry[:mail].any?
  puts "  tel  : #{entry[:telephoneNumber].first}" if entry[:telephoneNumber].any?
  puts ""
end

# on teste si on peut se bind avec un vrai utilisateur (einstein)
puts "--- Test de bind avec einstein ---\n\n"

ldap_einstein = Net::LDAP.new(
  host: host,
  port: port,
  auth: {
    method: :simple,
    username: "uid=einstein,dc=example,dc=com",
    password: "password"
  }
)

if ldap_einstein.bind
  puts "Bind OK pour einstein"
else
  puts "Bind raté pour einstein : #{ldap_einstein.get_operation_result.message}"
end
