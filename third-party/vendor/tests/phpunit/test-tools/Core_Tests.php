<?php

namespace WP2FA_Vendor\WP2FA\Core;

/**
 * This is a very basic test case to get things started. You should probably rename this and make
 * it work for your project. You can use all the tools provided by WP Mock and Mockery to create
 * your tests. Coverage is calculated against your includes/ folder, so try to keep all of your
 * functional code self contained in there.
 *
 * References:
 *   - http://phpunit.de/manual/current/en/index.html
 *   - https://github.com/padraic/mockery
 *   - https://github.com/10up/wp_mock
 */
use WP2FA_Vendor\WP2FA as Base;
class Core_Tests extends \WP2FA_Vendor\WP2FA\TestCase
{
    protected $testFiles = ['functions/core.php'];
    /**
     * Test load method.
     */
    public function test_setup()
    {
        // Setup
        \WP2FA_Vendor\WP_Mock::expectActionAdded('init', 'WP2FA_Vendor\\WP2FA\\Core\\i18n');
        \WP2FA_Vendor\WP_Mock::expectActionAdded('init', 'WP2FA_Vendor\\WP2FA\\Core\\init');
        \WP2FA_Vendor\WP_Mock::expectAction('wp_2fa_loaded');
        // Act
        setup();
        // Verify
        $this->assertConditionsMet();
    }
    /**
     * Test internationalization integration.
     */
    public function test_i18n()
    {
        // Setup
        \WP2FA_Vendor\WP_Mock::userFunction('get_locale', array('times' => 1, 'args' => array(), 'return' => 'en_US'));
        \WP2FA_Vendor\WP_Mock::onFilter('plugin_locale')->with('en_US', 'wp-2fa')->reply('en_US');
        \WP2FA_Vendor\WP_Mock::userFunction('load_textdomain', array('times' => 1, 'args' => array('wp-2fa', 'lang_dir/wp-2fa/wp-2fa-en_US.mo')));
        \WP2FA_Vendor\WP_Mock::userFunction('plugin_basename', array('times' => 1, 'args' => array('path'), 'return' => 'path'));
        \WP2FA_Vendor\WP_Mock::userFunction('load_plugin_textdomain', array('times' => 1, 'args' => array('wp-2fa', \false, 'path/languages/')));
        // Act
        i18n();
        // Verify
        $this->assertConditionsMet();
    }
    /**
     * Test initialization method.
     */
    public function test_init()
    {
        // Setup
        \WP2FA_Vendor\WP_Mock::expectAction('wp_2fa_init');
        // Act
        init();
        // Verify
        $this->assertConditionsMet();
    }
    /**
     * Test activation routine.
     */
    public function test_activate()
    {
        // Setup
        \WP2FA_Vendor\WP_Mock::userFunction('flush_rewrite_rules', array('times' => 1));
        // Act
        activate();
        // Verify
        $this->assertConditionsMet();
    }
    /**
     * Test deactivation routine.
     */
    public function test_deactivate()
    {
        // Setup
        // Act
        deactivate();
        // Verify
        $this->assertTrue(\true);
        // Replace with actual assertion
    }
}
