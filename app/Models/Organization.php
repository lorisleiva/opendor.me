<?php

namespace App\Models;

use App\Eloquent\Concerns\Blockable;
use App\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;

/**
 * App\Models\Organization.
 *
 * @property int $id
 * @property string $name
 * @property \Carbon\Carbon|null $blocked_at
 * @property \App\Enums\BlockReason|null $block_reason
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string|null $full_name
 * @property bool|null $is_verified
 * @property string|null $description
 * @property string|null $location
 * @property string|null $twitter
 * @property string|null $website
 * @property-read string $profile_url
 * @property-read string $avatar_url
 * @property-read string $github_url
 * @property-read string|null $twitter_url
 * @property-read string $display_name
 * @property-read bool $is_blocked
 * @property-read \Illuminate\Support\Collection $languages
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $members
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Repository[] $repositories
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Organization newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Organization newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Organization query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Organization extends Model implements Sitemapable
{
    use CrudTrait;
    use Blockable;

    public $incrementing = false;

    protected $casts = [
        'id' => 'int',
        'is_verified' => 'bool',
    ];

    protected $appends = [
        'avatar_url',
        'profile_url',
    ];

    public static function fromGithub(array $data): self
    {
        return static::query()->withBlocked()->firstOrCreate(
            ['id' => $data['id']],
            ['name' => $data['login']]
        );
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->as('organization_user');
    }

    public function repositories(): MorphMany
    {
        return $this->morphMany(Repository::class, 'owner');
    }

    public function getProfileUrlAttribute(): string
    {
        return route('profile', ['profile' => $this->name]);
    }

    public function getAvatarUrlAttribute(): string
    {
        return "https://avatars.githubusercontent.com/u/{$this->id}?s=192";
    }

    public function getGithubUrlAttribute(): string
    {
        return "https://github.com/{$this->name}";
    }

    public function getTwitterUrlAttribute(): ?string
    {
        return $this->twitter ? "https://twitter.com/{$this->twitter}" : null;
    }

    public function getWebsiteAttribute(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return Str::start($url, 'https://');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?? Str::title($this->name);
    }

    public function getLanguagesAttribute(): Collection
    {
        return once(
            fn () => $this->repositories()
                ->distinct('language')
                ->orderBy('language')
                ->pluck('language')
        );
    }

    public function github(): PendingRequest
    {
        return $this->members()
                ->whereIsRegistered()
                ->inRandomOrder()
                ->first()
                ?->github() ?? Http::github();
    }

    public function toSitemapTag(): Url
    {
        return Url::create($this->profile_url)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY);
    }
}
