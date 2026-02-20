<?php

namespace App\Services;

use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;

class UserAgentParserService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    /**
     * Parse user agent from request and return detailed information
     *
     * @param Request $request
     * @return array
     */
    public function parse(Request $request): array
    {
        $userAgent = $request->header('User-Agent');
        
        if ($userAgent) {
            $this->agent->setUserAgent($userAgent);
        }

        return [
            'user_agent' => $userAgent,
            'ip_address' => $request->ip(),
            'device_type' => $this->getDeviceType(),
            'browser' => $this->agent->browser(),
            'browser_version' => $this->agent->version($this->agent->browser()),
            'platform' => $this->agent->platform(),
            'platform_version' => $this->agent->version($this->agent->platform()),
        ];
    }

    /**
     * Parse user agent string directly (without request object)
     *
     * @param string $userAgent
     * @param string|null $ipAddress
     * @return array
     */
    public function parseUserAgent(string $userAgent, ?string $ipAddress = null): array
    {
        $this->agent->setUserAgent($userAgent);

        return [
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
            'device_type' => $this->getDeviceType(),
            'browser' => $this->agent->browser(),
            'browser_version' => $this->agent->version($this->agent->browser()),
            'platform' => $this->agent->platform(),
            'platform_version' => $this->agent->version($this->agent->platform()),
        ];
    }

    /**
     * Determine the device type
     *
     * @return string
     */
    protected function getDeviceType(): string
    {
        if ($this->agent->isDesktop()) {
            return 'Desktop';
        }

        if ($this->agent->isTablet()) {
            return 'Tablet';
        }

        if ($this->agent->isMobile()) {
            return 'Mobile';
        }

        if ($this->agent->isRobot()) {
            return 'Bot';
        }

        return 'Unknown';
    }

    /**
     * Get browser name
     *
     * @return string|null
     */
    public function getBrowser(): ?string
    {
        return $this->agent->browser();
    }

    /**
     * Get browser version
     *
     * @return string|null
     */
    public function getBrowserVersion(): ?string
    {
        return $this->agent->version($this->agent->browser());
    }

    /**
     * Get platform (OS) name
     *
     * @return string|null
     */
    public function getPlatform(): ?string
    {
        return $this->agent->platform();
    }

    /**
     * Get platform version
     *
     * @return string|null
     */
    public function getPlatformVersion(): ?string
    {
        return $this->agent->version($this->agent->platform());
    }

    /**
     * Check if device is mobile
     *
     * @return bool
     */
    public function isMobile(): bool
    {
        return $this->agent->isMobile();
    }

    /**
     * Check if device is tablet
     *
     * @return bool
     */
    public function isTablet(): bool
    {
        return $this->agent->isTablet();
    }

    /**
     * Check if device is desktop
     *
     * @return bool
     */
    public function isDesktop(): bool
    {
        return $this->agent->isDesktop();
    }

    /**
     * Check if device is a bot/crawler
     *
     * @return bool
     */
    public function isBot(): bool
    {
        return $this->agent->isRobot();
    }

    /**
     * Get device name (e.g., iPhone, iPad, Samsung Galaxy)
     *
     * @return string|null
     */
    public function getDevice(): ?string
    {
        return $this->agent->device();
    }

    /**
     * Get robot name if it's a bot
     *
     * @return string|null
     */
    public function getRobot(): ?string
    {
        return $this->agent->robot();
    }
}