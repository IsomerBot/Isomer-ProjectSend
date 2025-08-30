<?php
/**
 * Terminology Mapping System
 * Changes UI terminology without breaking backend code
 */

class TerminologyMapper
{
    private static $mappings = [
        "clients" => "contacts",
        "client" => "contact",
        "groups" => "projects",
        "group" => "project",
        "Clients" => "Contacts",
        "Client" => "Contact",
        "Groups" => "Projects",
        "Group" => "Project",
        "CLIENTS" => "CONTACTS",
        "CLIENT" => "CONTACT",
        "GROUPS" => "PROJECTS",
        "GROUP" => "PROJECT",
        "Add clients group" => "Add project",
        "Clients Administration" => "Contacts Administration",
        "Manage clients" => "Manage contacts",
        "clients can" => "contacts can",
        "Clients can" => "Contacts can",
        "Select clients" => "Select contacts",
        "Add client" => "Add contact",
        "Edit client" => "Edit contact",
        "Client created successfully" => "Contact created successfully",
        "Client saved successfully" => "Contact saved successfully",
        "Group Name" => "Project Number",
        "Group Description" => "Project Description",
        "Members" => "Contacts",
        "Clients groups" => "Projects",
        "clients groups" => "projects",
    ];

    public static function map($text)
    {
        return str_replace(
            array_keys(self::$mappings),
            array_values(self::$mappings),
            $text
        );
    }
}

function tm($text)
{
    return TerminologyMapper::map($text);
}

function __tm($text, $domain = "cftp_admin")
{
    return tm(__($text, $domain));
}
?>
