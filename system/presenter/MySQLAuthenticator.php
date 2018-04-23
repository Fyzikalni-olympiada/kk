<?php

namespace Eywa\CMS\Presenters;

use dibi;
use \Nette\Security;

/**
 * @title MySQLAuthenticator
 */


class MySQLAuthenticator extends \Nette\Security\SimpleAuthenticator
{
    private $userList;
    function __construct()
    {
        $this->userList = dibi::query(
            'SELECT "1" AS "role", referent_email AS "email", refpass AS "pass_hash"
                FROM referents
                UNION (SELECT "2", email, perpass FROM persons WHERE enableLogin = 1)')->fetchAll();
        // check data:
        // SELECT * FROM
        //   (SELECT email,count(*) AS pocet FROM
        //     (SELECT "1" AS "role", referent_email AS "email", refpass AS "pass_hash"
        //       FROM referents
        //       UNION (SELECT "2", email, perpass FROM persons WHERE enableLogin=1)
        //     ORDER BY email) AS alluser
        //   GROUP BY email
        //   ORDER BY pocet DESC) AS allusercnt
        // WHERE pocet > 1
    }

    function authenticate( array $credentials)
    {
        $username = $credentials[self::USERNAME];
        $password = $credentials[self::PASSWORD];

        $hash = "";
        foreach ($this->userList as $user) {
            if( strcasecmp($username, $user['email']) == 0 ) {
                $hash = $user['pass_hash'];
                $role = $user['role'];
            }
        }

        if( $hash ===  crypt($password,$hash) )
            return  new \Nette\Security\Identity($username, $role=$role);
        throw new \Nette\Security\AuthenticationException("Invalid password.", self::INVALID_CREDENTIAL);
    }

    function setPass( $username, $newpass )
    {
        if( func_num_args() == 1)
            $newpass = $this->getPass();

        $hash = crypt( $newpass );
        foreach ($this->userList as $user) {
            if( strcasecmp($username, $user['email']) == 0){
                if( $user['role'] == 1 ) {
                    dibi::query("UPDATE referents SET refpass=%s WHERE referent_email = %s;", $hash, $username);
                    return $newpass;
                }
                if( $user['role'] == 2 ) {
                    dibi::query("UPDATE persons SET perpass=%s WHERE email = %s;", $hash, $username);
                    return $newpass;
                }
            }
        }
        return null;
    }

    function getPass()
    {
        $charactersA = 'bcdfghjklmnprstvz';
        $charactersB = 'aeiou';
        $charactersLengthA = strlen($charactersA);
        $charactersLengthB = strlen($charactersB);
        $randomString = '';
        for ($i = 0; $i < 3; $i++) {
            $randomString .= $charactersA[rand(0, $charactersLengthA - 1)];
            $randomString .= $charactersB[rand(0, $charactersLengthB - 1)];
        }
        $randomString .= $charactersA[rand(0, $charactersLengthA - 1)];
        return $randomString;
    }
}
