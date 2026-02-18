<?php
// Configuration LDAP
// Pour changer de mode : mettre 'test' ou 'ul'
//   - test : serveur public forumsys (marche partout)
//   - ul   : LDAP de l'UL (nécessite le réseau de l'IUT)

define('LDAP_MODE', 'test');

$LDAP_CONFIGS = [

    // serveur public forumsys, pas de chiffrement
    // comptes dispo : einstein, tesla, newton, etc. (mdp : password)
    'test' => [
        'name'              => 'Serveur de test (forumsys)',
        'servers'           => ['ldap.forumsys.com'],
        'port'              => 389,
        'use_tls'           => false,
        'bind_format'       => 'uid=%s,dc=example,dc=com',
        'base_dns'          => ['dc=example,dc=com'],
        'search_attribute'  => 'uid',
        'display_attributes'=> ['uid', 'cn', 'mail', 'telephoneNumber'],
        'rattachement_groups' => [],
    ],

    // LDAPS sur le réseau de l'Université de Lorraine
    // deux serveurs avec failover
    'ul' => [
        'name'              => 'LDAP Université de Lorraine',
        'servers'           => [
            'montet-dc1.ad.univ-lorraine.fr',
            'montet-dc2.ad.univ-lorraine.fr',
        ],
        'port'              => 636,
        'use_tls'           => true,
        'bind_format'       => '%s@etu.univ-lorraine.fr',
        'base_dns'          => [
            'OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr',
            'OU=Personnels,OU=_Utilisateurs,OU=UL,DC=ad,DC=univ-lorraine,DC=fr',
        ],
        'search_attribute'  => 'sAMAccountName',
        'display_attributes'=> ['cn', 'mail', 'sAMAccountName', 'memberOf', 'distinguishedName'],
        'rattachement_groups' => [
            'GGA_STP_FHBAB' => 'Département Informatique',
            'GGA_STP_FHB'   => 'IUT Nancy-Charlemagne',
        ],
    ],
];

function get_config(): array {
    global $LDAP_CONFIGS;
    return $LDAP_CONFIGS[LDAP_MODE];
}
