<?php


namespace Loxodo\App;

/**
 * Implements the security-settings from the config/accessmapper.json to the uri
 *
 * Class Guard
 * @package App
 */
class Guard
{

    /**
     * @var User
     */
    protected $user;
    protected $guarded = array();

    /**
     * Guard constructor.
     * @param $user
     */
    public function __construct(User $user, $guarded)
    {
        $this->user = $user;
        foreach($guarded as $map){
            $this->guarded[$this->convertDirectory($map->directory)] = $map;
        }
    }

    /**
     * Is the Folder registered in the accessmapper.
     *
     * @param $directory
     * @return bool
     */
    public function isGuarded($directory)
    {
        return isset($this->guarded[$this->convertDirectory($directory)]);
    }

    /**
     * Is there a redirect for the given user?
     *
     * @param $directory
     * @return bool
     */
    public function hasRedirect($directory){
        if($this->isGuarded($directory) && $this->user->isLoggedIn()){
            $map = $this->guarded[$this->convertDirectory($directory)];
            $profile = $this->user->getProfile();
            return isset($map->profiles->$profile);
        }
        return false;

    }

    /**
     * Get the destinationdirectory for the given user and the directory.
     *
     * @param $directory
     * @return string
     */
    public function getDestination($directory)
    {
        if(isset($this->guarded[$this->convertDirectory($directory)])){
            $map = $this->guarded[$this->convertDirectory($directory)];
            $profile = $this->user->getProfile();
            if(isset($map->profiles->$profile)){
                return $map->profiles->$profile;
            }
        }
        return $directory;
    }

    /**
     * Is the given folder CSRF-protected following the accessmapper?
     * By default a folder returns the setting from the config.
     * @param $directory
     * return Bool
     */
    public function hasCsrfProtection($directory)
    {
        $folder = $this->convertDirectory($directory);
        if($this->isGuarded($folder)){
            return isset($this->guarded[$folder]->csrf) &&  $this->guarded[$folder]->csrf === true;
        }
        return CSRF_PROTECTION;

    }

    /**
     * Return a array-friendly string
     * @param $directory
     * @return string
     */
    protected function convertDirectory($directory)
    {
        return (string)str_replace('/', '__', $directory);
    }

}