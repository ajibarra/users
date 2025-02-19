<?php
declare(strict_types=1);

/**
 * Copyright 2010 - 2019, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010 - 2018, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\Users\View\Helper;

use Cake\Core\Configure;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\View\Helper;
use CakeDC\Users\Utility\UsersUrl;
use InvalidArgumentException;

/**
 * User helper
 *
 * @property \CakeDC\Users\View\Helper\AuthLinkHelper $AuthLink
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Cake\View\Helper\FormHelper $Form
 */
class UserHelper extends Helper
{
    protected array $helpers = ['Html', 'Form', 'CakeDC/Users.AuthLink'];

    /**
     * @inheritDoc
     */
    protected array $_defaultConfig = [];

    /**
     * Social login link
     *
     * @param string $name name
     * @param array $options options
     * @return string
     */
    public function socialLogin(string $name, array $options = []): string
    {
        if (empty($options['label'])) {
            $options['label'] = __d('cake_d_c/users', 'Sign in with');
        }
        $icon = $this->Html->tag('i', '', [
            'class' => 'fa fa-' . strtolower($name),
        ]);

        if (isset($options['title'])) {
            $providerTitle = $options['title'];
        } else {
            $providerTitle = $options['label'] . ' ' . Inflector::camelize($name);
        }

        $providerClass = 'btn btn-social btn-' . strtolower($name);
        $optionClass = $options['class'] ?? null;
        if ($optionClass) {
            $providerClass .= " $optionClass";
        }

        return $this->Html->link($icon . $providerTitle, "/auth/$name", [
            'escape' => false, 'class' => $providerClass,
        ]);
    }

    /**
     * All available Social Login Icons
     *
     * @param array $providerOptions Provider link options.
     * @return array Links to Social Login Urls
     */
    public function socialLoginList(array $providerOptions = []): array
    {
        if (!Configure::read('Users.Social.login')) {
            return [];
        }
        $outProviders = [];
        $providers = Configure::read('OAuth.providers');
        foreach ($providers as $provider => $options) {
            if (
                !empty($options['options']['redirectUri']) &&
                !empty($options['options']['clientId']) &&
                !empty($options['options']['clientSecret'])
            ) {
                if (isset($providerOptions[$provider])) {
                    $options['options'] = Hash::merge($options['options'], $providerOptions[$provider]);
                }

                $outProviders[] = $this->socialLogin($provider, $options['options']);
            }
        }

        return $outProviders;
    }

    /**
     * Logout link
     *
     * @param string|null $message logout message info.
     * @param array $options Array with option data.
     * @return string
     */
    public function logout(?string $message = null, array $options = []): string
    {
        $url = UsersUrl::actionUrl('logout');
        $title = empty($message) ? __d('cake_d_c/users', 'Logout') : $message;

        return $this->AuthLink->link($title, $url, $options);
    }

    /**
     * Welcome display
     *
     * @return string|null
     */
    public function welcome(): ?string
    {
        $identity = $this->getView()->getRequest()->getAttribute('identity');
        if (!$identity) {
            return null;
        }

        $profileUrl = Configure::read('Users.Profile.route');
        $title = $identity['first_name'] ?? null;
        $title = $title ?: ($identity['username'] ?? null);
        $title = is_array($title) ? '-' : (string)$title;
        $label = __d(
            'cake_d_c/users',
            'Welcome, {0}',
            $this->AuthLink->link($title, $profileUrl)
        );

        return $this->Html->tag('span', $label, ['class' => 'welcome']);
    }

    /**
     * Add reCaptcha script
     *
     * @return void
     */
    public function addReCaptchaScript(): void
    {
        $this->Html->script('https://www.google.com/recaptcha/api.js', [
            'block' => 'script',
        ]);
    }

    /**
     * @return void
     */
    public function addPasswordMeterScript(): void
    {
        $this->Html->script('CakeDC/Users.pswmeter', [
            'block' => 'script',
        ]);
    }

    /**
     * @return string
     */
    public function addPasswordMeter(): string
    {
        $this->addPasswordMeterScript();
        $requiredScore = Configure::read('Users.passwordMeter.requiredScore', 3);
        $messagesList = json_encode(
            Configure::read(
                'Users.passwordMeter.messagesList',
                ['Empty password', 'Too simple', 'Simple', 'That\'s OK', 'Great password!']
            )
        );
        $pswMinLength = Configure::read('Users.passwordMinLength', 8);
        $showMessage = Configure::read('Users.passwordMeter.showMessage', true) ? 'true' : 'false';
        $script = $this->Html->scriptBlock("
            const requiredScore = $requiredScore;
            const messagesList = $messagesList;
            const pswMinLength = $pswMinLength;
            const showMessage = $showMessage;
        ", ['defer' => true]);

        return $this->Html->tag('div', '', ['id' => 'pswmeter']) .
            $this->Html->tag('div', '', ['id' => 'pswmeter-message']) . $script;
    }

    /**
     * Add reCaptcha to the form
     *
     * @return mixed
     */
    public function addReCaptcha(): mixed
    {
        if (!Configure::read('Users.reCaptcha.key')) {
            return $this->Html->tag(
                'p',
                __d(
                    'cake_d_c/users',
                    'reCaptcha is not configured! Please configure Users.reCaptcha.key'
                )
            );
        }
        $this->addReCaptchaScript();
        $version = Configure::read('Users.reCaptcha.version', 2);
        $method = "addReCaptchaV$version";
        if (method_exists($this, $method)) {
            try {
                $this->Form->unlockField('g-recaptcha-response');
            } catch (\Exception $e) {
            }

            return $this->{$method}();
        }
        throw new InvalidArgumentException(
            __d('cake_d_c/users', 'reCaptcha version is wrong. Please configure Users.reCaptcha.version as 2 or 3')
        );
    }

    /**
     * Add required element for reCaptcha v2
     *
     * @return string
     */
    private function addReCaptchaV2(): string
    {
        deprecationWarning('14.2.0', 'reCaptcha version 3 will be used as default in version 15.0.0');

        return $this->Html->tag('div', '', [
            'class' => 'g-recaptcha',
            'data-sitekey' => Configure::read('Users.reCaptcha.key'),
            'data-theme' => Configure::read('Users.reCaptcha.theme') ?: 'light',
            'data-size' => Configure::read('Users.reCaptcha.size') ?: 'normal',
            'data-tabindex' => Configure::read('Users.reCaptcha.tabindex') ?: '3',
        ]);
    }

    /**
     * Add required script for reCaptcha v3
     */
    private function addReCaptchaV3(): void
    {
        $this->Html->script('CakeDC/Users.reCaptchaV3', [
            'block' => 'script',
        ]);
    }

    /**
     * Add required options for reCaptcha v3
     *
     * @param string $title
     * @param array  $options
     * @return string
     */
    public function button(string $title, array $options = []): string
    {
        $key = Configure::read('Users.reCaptcha.key');
        if ($key && Configure::read('Users.reCaptcha.version', 2) === 3) {
            $options = array_merge($options, [
                'class' => 'g-recaptcha',
                'data-sitekey' => $key,
                'data-callback' => 'onSubmit',
                'data-action' => 'submit',
            ]);
        }

        return $this->Form->button($title, $options);
    }

    /**
     * Generate a link if the target url is authorized for the logged in user
     *
     * @deprecated Since 3.2.1. Use AuthLinkHelper::link() instead
     * @param string $title link's title.
     * @param array|string|null $url url that the user is making request.
     * @param array $options Array with option data.
     * @return string
     */
    public function link(string $title, array|string|null $url = null, array $options = []): string
    {
        trigger_error(
            'UserHelper::link() deprecated since 3.2.1. Use AuthLinkHelper::link() instead',
            E_USER_DEPRECATED
        );

        return $this->AuthLink->link($title, $url, $options);
    }

    /**
     * Create links for all social providers enabled social link (connect)
     *
     * @param string $name        Provider name in lowercase
     * @param array  $provider    Provider configuration
     * @param bool   $isConnected User is connected with this provider
     * @return string
     */
    public function socialConnectLink(string $name, array $provider, bool $isConnected = false): string
    {
        $optionClass = $provider['options']['class'] ?? null;
        $linkClass = 'btn btn-social btn-' . strtolower($name) . ($optionClass ? ' ' . $optionClass : '');
        if ($isConnected) {
            $title = __d('cake_d_c/users', 'Connected with {0}', Inflector::camelize($name));

            return "<a class=\"$linkClass disabled\"><span class=\"fa fa-$name\"></span> $title</a>";
        }

        $title = __d('cake_d_c/users', 'Connect with {0}', Inflector::camelize($name));

        return $this->Html->link(
            "<span class=\"fa fa-$name\"></span> $title",
            "/link-social/$name",
            [
                'escape' => false,
                'class' => $linkClass,
            ]
        );
    }

    /**
     * Create links for all social providers enabled social link (connect)
     *
     * @param array $socialAccounts All social accounts connected by a user.
     * @return string
     */
    public function socialConnectLinkList(array $socialAccounts = []): string
    {
        if (!Configure::read('Users.Social.login')) {
            return '';
        }
        $html = '';
        $connectedProviders = array_map(
            function ($item) {
                return strtolower($item->provider);
            },
            (array)$socialAccounts
        );

        $providers = Configure::read('OAuth.providers');
        foreach ($providers as $name => $provider) {
            if (
                !empty($provider['options']['callbackLinkSocialUri']) &&
                !empty($provider['options']['linkSocialUri']) &&
                !empty($provider['options']['clientId']) &&
                !empty($provider['options']['clientSecret'])
            ) {
                $html .= $this->socialConnectLink($name, $provider, in_array($name, $connectedProviders));
            }
        }

        return $html;
    }
}
