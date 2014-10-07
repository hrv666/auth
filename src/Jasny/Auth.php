<?php

namespace Jasny;

use Jasny\Auth\User;

/**
 * Authentication
 */
abstract class Auth
{
    /**
     * Secret word for creating a verification hash
     * @var string
     */
    protected static $secret;

    /**
     * Current user
     * @var User
     */
    protected static $user;
    
    
    /**
     * Fetch a user by ID
     * 
     * @param int $id
     * @return User
     */
    abstract public static function fetchUserById($id);

    /**
     * Fetch a user by username
     * 
     * @param string $username
     * @return User
     */
    abstract public static function fetchUserByUsername($username);
    
    
    /**
     * Generate a password
     * 
     * @param string $password
     * @param string $salt      Use specific salt to verify existing password
     */
    public static function password($password, $salt=null)
    {
        return isset($salt) ? crypt($password, $salt) : password_hash($password, PASSWORD_BCRYPT);
    }
    
    /**
     * Login with username and password
     * 
     * @param string $username
     * @param string $password
     * @return boolean
     */
    public static function login($username, $password)
    {
        $user = static::fetchUserByUsername($username);
        if (!isset($user) || $user->getPassword() !== self::password($password, $user->getPassword())) return false;
        
        return static::setUser($user);
    }
    
    /**
     * Logout
     */
    public static function logout()
    {
       self::$user = null;
       unset($_SESSION['auth_uid']);
    }
    
    
    /**
     * Set the current user
     * 
     * @param User $user
     * @return boolean
     */
    public static function setUser(User $user)
    {
        if (!$user->onLogin()) return false;
        
        self::$user = $user;
        $_SESSION['auth_uid'] = $user->getId();
        return true;
    }

    /**
     * Get current authenticated user
     * 
     * @return User
     */
    public static function user()
    {
        if (!isset(self::$user) && isset($_SESSION['auth_uid'])) {
            self::$user = static::fetchUserById($_SESSION['auth_uid']);
        }
        
        return self::$user;
    }
    
    
    /**
     * Get secret word
     * 
     * @return string
     */
    protected static function getSecret()
    {
        if (!isset(static::$secret)) throw new \Exception("Auth secret isn't set");
        return static::$secret;
    }
    
    /**
     * Generate a confirmation hash
     * 
     * @param User $user
     * @return string
     */
    public static function generateConfirmationHash($user)
    {
        $id = $user->getId();
        
        return sprintf('%010s', substr(base_convert(md5($id . self::getSecret()), 16, 36), -10) .
            base_convert($id, 10, 36));
    }
    
    /**
     * Get user by confirmation hash
     * 
     * @parma string $hash  confirmation hash
     * @return User
     */
    public function fetchForConfirmation($hash)
    {
        $id = base_convert(substr($hash, 10), 36, 10);
        if (self::generateConfirmationHash($id) != $hash) return null; // invalid hash
        
        return static::fetchUserById($id);
    }
    
    /**
     * Generate a hash to reset the password
     * 
     * @param User $user
     * @return string
     */
    public static function generatePasswordResetHash($user)
    {
        $id = $user->getId();
        $password = $user->getPassword();
        
        return sprintf('%010s', substr(base_convert(md5($id . static::getSecret() . $password), 16, 36), -10)
            . base_convert($id, 10, 36));
    }
    
    /**
     * Fetch a user for a password reset
     * 
     * @param string $hash
     * @return User
     */
    public function fetchForPasswordReset($hash)
    {
        $id = base_convert(substr($hash, 10), 36, 10);
        
        $user = static::fetchUserById($id);
        if (!$user || self::generatePasswordResetHash($id, $user->getPassword()) != $hash) return null; // invalid hash
        
        return $user;
    }
}