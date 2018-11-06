<?php

namespace Autogit;

use Cz\Git\GitRepository;
use C;

class Autogit extends GitRepository
{
    static public $instance = null;

    protected $remoteBranch;
    protected $remoteName;

    public function __construct()
    {
        parent::__construct(kirby()->roots()->content());

        $this->remoteBranch = c::get('autogit.remote.branch', 'master');
        $this->remoteName = c::get('autogit.remote.name', 'origin');

        static::$instance = $this;
    }

    static public function instance()
    {
        return static::$instance = is_null(static::$instance)
            ? new static
            : static::$instance;
    }

    public function save(...$params)
    {
        // add changes
        $this->addFile(kirby()->roots()->content());

        // do not try to commit when the working copy is clean
        if (!$this->hasChanges()) {
            return;
        }

        // get commit message
        $message = $this->getMessage($params[0], array_slice($params, 1));

        // commit changes
        $this->commit($message, ['--author' => $this->author()]);

        // pull latest changes
        $this->pull();

        // push 
        $this->push();
    }

    public function hasRemote($remoteName = null)
    {
        $remoteName = $remoteName ? $remoteName : $this->remoteName;

        try {
            $this->execute(['config', '--get', "remote.{$remoteName}.url"]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    protected function author()
    {
        $user = site()->user();
        $preferUser = c::get('autogit.panel.user', true);
        $userName = c::get('autogit.user.name', 'Kirby Auto Git');
        $userEmail = c::get('autogit.user.email', 'autogit@localhost');

        if ($preferUser && $user && $user->firstname()) {
            $userName = $user->firstname();

            if ($user->lastname()) {
                $userName .= ' ' . $user->lastname();
            }

            $userEmail = $user->email();
        }

        return "{$userName} <{$userEmail}>";
    }

    protected function getMessage($key, $params)
    {
        $translation = c::get('autogit.translation', false);

        if (!$translation) {
            $translations = require __DIR__ . DS . 'translations.php';
            $language = kirby()->option('panel.language', 'en');

            if (!array_key_exists($language, $translations)) {
                $language = 'en';
            }

            $translation = $translations[$language];
        }

        array_unshift($params, $translation[$key]);

        return sprintf(...$params);
    }
}
