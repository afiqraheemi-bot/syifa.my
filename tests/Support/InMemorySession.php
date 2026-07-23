<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Session\Session;

/** A minimal, real, in-memory `Session` implementation for unit tests. */
final class InMemorySession implements Session
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function getName() {}

    public function setName($name) {}

    public function getId() {}

    public function setId($id) {}

    public function start() {}

    public function save() {}

    public function all()
    {
        return $this->data;
    }

    public function exists($key)
    {
        return array_key_exists($key, $this->data);
    }

    public function has($key)
    {
        return isset($this->data[$key]);
    }

    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        unset($this->data[$key]);

        return $value;
    }

    public function put($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $arrayKey => $arrayValue) {
                $this->data[$arrayKey] = $arrayValue;
            }

            return;
        }
        $this->data[$key] = $value;
    }

    public function flash(string $key, $value = true) {}

    public function token() {}

    public function regenerateToken() {}

    public function remove($key)
    {
        $value = $this->data[$key] ?? null;
        unset($this->data[$key]);

        return $value;
    }

    public function forget($keys)
    {
        foreach ((array) $keys as $key) {
            unset($this->data[$key]);
        }
    }

    public function flush()
    {
        $this->data = [];
    }

    public function invalidate() {}

    public function regenerate($destroy = false) {}

    public function migrate($destroy = false) {}

    public function isStarted()
    {
        return true;
    }

    public function previousUrl() {}

    public function setPreviousUrl($url) {}

    public function getHandler() {}

    public function handlerNeedsRequest()
    {
        return false;
    }

    public function setRequestOnHandler($request) {}
}
