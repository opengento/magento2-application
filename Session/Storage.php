<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
declare(strict_types=1);

namespace Opengento\Application\Session;

use Magento\Framework\Session\Storage as FrameworkStorage;

/**
 * Self-healing session storage for sessions whose owners access $this->storage DIRECTLY.
 *
 * The lazy-start plugin (StartSessionOnAccess) re-binds a session's storage on first access — but only
 * for access paths that go through the SessionManager (__call, getData, isLoggedIn, getCustomerId).
 * Some modules read/write the storage object directly, bypassing every SessionManager entry point:
 * e.g. Magento\TwoFactorAuth\Model\TfaSession::isGranted()/grantAccess() call $this->storage->getData()/
 * setData() straight on the storage. On a persistent worker the storage's _data was reset to [] between
 * requests (Resetter), and with no SessionManager call there is nothing to re-bind it — so reads return
 * empty and writes land in an array that is never aliased into $_SESSION (lost at commit). Symptom:
 * admin TFA code accepted but the grant never persists — endless bounce back to the TFA page.
 *
 * This subclass re-binds lazily at the storage level: when the in-memory _data is empty while the LIVE
 * $_SESSION carries data for this namespace (and the PHP session is active), re-run init($_SESSION) to
 * copy the data in and re-alias $_SESSION[$namespace] to this storage.
 *
 * Deliberately conservative:
 *  - only fires while the PHP session is ACTIVE — after a mid-request write-close (PageCache
 *    depersonalize) it stays inert, matching StartSessionOnAccess::isClosedMidRequest();
 *  - only re-attaches IN-MEMORY $_SESSION data — it never re-opens the persisted store, so a
 *    depersonalize-cleared namespace (cleared through the live alias) stays cleared: FPM parity;
 *  - reaches only sessions using this BASE storage. Sessions with their own storage subclass
 *    (e.g. Magento\Customer\Model\Session\Storage) are NOT covered by this preference — those are
 *    handled at the SessionManager level (StartSessionOnAccess, incl. the getCustomerId repair).
 */
#[\Magento\Framework\ObjectManager\Attribute\NonLazy]
class Storage extends FrameworkStorage
{
    /** @var bool Re-entrancy guard: init() itself calls setData(). */
    private bool $rebinding = false;

    /**
     * @param string $key
     * @param bool $clear
     * @return mixed
     */
    public function getData($key = '', $clear = false)
    {
        $this->rebindIfDetached();
        return parent::getData($key, $clear);
    }

    /**
     * @param string|array $key
     * @param mixed $value
     * @return $this
     */
    public function setData($key, $value = null)
    {
        $this->rebindIfDetached();
        return parent::setData($key, $value);
    }

    /**
     * @param string|null $key
     * @return $this
     */
    public function unsetData($key = null)
    {
        $this->rebindIfDetached();
        return parent::unsetData($key);
    }

    /**
     * Re-attach this storage to the live $_SESSION namespace after a between-request reset.
     *
     * @return void
     */
    private function rebindIfDetached(): void
    {
        if ($this->rebinding || !empty($this->_data)) {
            return;
        }
        $namespace = $this->getNamespace() ?? '';
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[$namespace])) {
            $this->rebinding = true;
            try {
                $this->init($_SESSION);
            } finally {
                $this->rebinding = false;
            }
        }
    }

    /**
     * @return void
     */
    public function _resetState(): void
    {
        parent::_resetState();
        $this->rebinding = false;
    }
}
