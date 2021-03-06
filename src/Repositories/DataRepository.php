<?php

namespace DoubleThreeDigital\SimpleCommerce\Repositories;

use Exception;
use Statamic\Contracts\Entries\Entry as EntriesEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Http\Resources\API\EntryResource;
use Statamic\Sites\Site as ASite;

trait DataRepository
{
    public string $id = '';
    public string $title = '';
    public string $slug = '';
    public array $data = [];
    public ASite $site;

    public function make(): self
    {
        $this->id = Stache::generateId();
        $this->site = Site::current();

        return $this;
    }

    public function find(string $id): self
    {
        $this->id = $id;

        $entry = $this->entry();

        if ($entry->existsIn(Site::current()->handle()) && $entry->locale() !== Site::current()->handle()) {
            $entry = $entry->in(Site::current()->handle());
        }

        $this->title = $entry->title;
        $this->slug = $entry->slug();
        $this->data = $entry->data()->toArray();

        return $this;
    }

    public function title(string $title = ''): self
    {
        if ($title === '') {
            return $this->title;
        }

        $this->title = $title;

        return $this;
    }

    public function slug(string $slug = ''): self
    {
        if ($slug === '') {
            return $this->slug;
        }

        $this->title = $slug;

        return $this;
    }

    public function data(array $data = []): self
    {
        if ($data === []) {
            return $this->data;
        }

        $this->data = $data;

        return $this;
    }

    public function site($site = null): self
    {
        if (is_null($site)) {
            return $this->site;
        }

        if (! $site instanceof ASite) {
            $site = Site::get($site);
        }

        $this->site = $site;

        return $this;
    }

    public function update(array $data, bool $mergeData = true): self
    {
        if ($mergeData) {
            $data = array_merge($this->data, $data);
        }

        $this->entry()
            ->data($data)
            ->save();

        return $this;
    }

    public function entry(): EntriesEntry
    {
        $entry = Entry::find($this->id);

        if (!$entry) {
            throw new Exception("Entry could not be found. ID: {$this->id}");
        }

        return $entry;
    }

    public function toArray(): array
    {
        return [];
    }

    public function toResource(): EntryResource
    {
        return new EntryResource($this->entry());
    }

    public function get(string $key)
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->data[$key];
    }

    public function set(string $key, $value): self
    {
        $this->data[$key] = $value;
        $this->entry()->set($key, $value)->save();

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public static function bindings(): array
    {
        return [];
    }
}
