<?php

namespace Deployer\Ssh;

use Deployer\Host\Host;

/**
 * @author Michael Woodward <mikeymike.mw@gmail.com>
 */
class Arguments
{
    /**
     * @var array
     */
    private $flags = [];

    /**
     * @var array
     */
    private $options = [];

    public function getCliArguments() : string
    {
        $boolFlags  = array_keys(array_filter($this->flags, 'is_null'));

        $valueFlags = array_filter($this->flags);
        $valueFlags = array_map(function ($key, $value) {
            return "$key $value";
        }, array_keys($valueFlags), $valueFlags);

        $options    = array_map(function ($key, $value) {
            return "-o $key=$value";
        }, array_keys($this->options), $this->options);

        $args = sprintf('%s %s %s', implode(' ', $boolFlags), implode(' ', $valueFlags), implode(' ', $options));

        return trim(preg_replace('!\s+!', ' ', $args));
    }

    public function getOption(string $option) : string
    {
        return $this->options[$option] ?? '';
    }

    /**
     * @param string $flag
     * @return bool|mixed
     */
    public function getFlag(string $flag)
    {
        if (!array_key_exists($flag, $this->flags)) {
            return false;
        }

        return $this->flags[$flag] ?? true;
    }

    public function withFlags(array $flags) : Arguments
    {
        $clone = clone $this;
        $clone->flags = $this->buildFlagsFromArray($flags);

        return $clone;
    }

    public function withOptions(array $options) : Arguments
    {
        $clone = clone $this;
        $clone->options = $options;

        return $clone;
    }

    public function withFlag(string $flag, string $value = null) : Arguments
    {
        $clone = clone $this;
        $clone->flags = array_merge($this->flags, [$flag => $value]);

        return $clone;
    }

    public function withOption(string $option, string $value) : Arguments
    {
        $clone = clone $this;
        $clone->options = array_merge($this->options, [$option => $value]);

        return $clone;
    }

    public function withDefaults(Arguments $defaultOptions) : Arguments
    {
        $clone = clone $this;
        $clone->options = array_merge($defaultOptions->options, $this->options);
        $clone->flags = array_merge($defaultOptions->flags, $this->flags);

        return $clone;
    }

    public function withMultiplexing(Host $host) : Arguments
    {
        $controlPath = $this->generateControlPath($host);

        $multiplexDefaults = (new Arguments)->withOptions([
            'ControlMaster'  => 'auto',
            'ControlPersist' => '60',
            'ControlPath'    => $controlPath,
        ]);

        return $this->withDefaults($multiplexDefaults);
    }

    /**
     * Return SSH multiplexing control path
     *
     * When ControlPath is longer than 104 chars we can get:
     *
     *     SSH Error: unix_listener: too long for Unix domain socket
     *
     * So try to get as descriptive path as possible.
     * %C is for creating hash out of connection attributes.
     *
     * @param Host $host
     * @return string ControlPath
     * @throws Exception
     */
    private function generateControlPath(Host $host)
    {
        $connectionData = "$host{$host->getPort()}";
        $tryLongestPossible = 0;
        $controlPath = '';
        do {
            switch ($tryLongestPossible) {
                case 1:
                    $controlPath = "~/.ssh/deployer_mux_$connectionData";
                    break;
                case 2:
                    $controlPath = "~/.ssh/deployer_mux_%C";
                    break;
                case 3:
                    $controlPath = "~/deployer_mux_$connectionData";
                    break;
                case 4:
                    $controlPath = "~/deployer_mux_%C";
                    break;
                case 5:
                    $controlPath = "~/mux_%C";
                    break;
                case 6:
                    throw new Exception("The multiplexing control path is too long. Control path is: $controlPath");
                default:
                    $controlPath = "~/.ssh/deployer_mux_$connectionData";
            }
            $tryLongestPossible++;
        } while (strlen($controlPath) > 104); // Unix socket max length

        return $controlPath;
    }

    private function buildFlagsFromArray($flags) : array
    {
        $boolFlags = array_filter(array_map(function ($key, $value) {
            if (is_int($key)) {
                return $value;
            }

            if (null === $value) {
                return $key;
            }
        }, array_keys($flags), $flags));

        $valueFlags = array_filter($flags, function ($key, $value) {
            return is_string($key) && is_string($value);
        }, ARRAY_FILTER_USE_BOTH);

        return array_merge(array_fill_keys($boolFlags, null), $valueFlags);
    }

    public function __toString() : string
    {
        return $this->getCliArguments();
    }
}
