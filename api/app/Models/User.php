<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) strtolower(Str::ulid());
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'has_password',
        'email_verified',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'preferences' => 'array',
        ];
    }

    /**
     * Determine if the user has set their password
     *
     * @return bool
     */
    public function getHasPasswordAttribute()
    {
        return !is_null($this->password);
    }

    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function getEmailVerifiedAttribute()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function profilePhotoUrl(): Attribute
    {
        return Attribute::get(function () {
            return $this->profile_photo_path
                ? config('app.url_accounts') . '/storage/' . $this->profile_photo_path
                : $this->defaultProfilePhotoUrl();
        });
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     *
     * @return string
     */
    protected function defaultProfilePhotoUrl()
    {
        $name = trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Get all of the teams the user belongs to.
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Determine if the user owns the given team.
     */
    public function ownsTeam($team): bool
    {
        if ($team === null) {
            return false;
        }

        return $this->id == $team->user_id;
    }

    /**
     * Determine if the user belongs to a given team.
     */
    public function belongsToTeam($team): bool
    {
        if ($team === null) {
            return false;
        }

        return $this->teams->contains($team) || $this->ownsTeam($team);
    }

    /**
     * Add a user to a team with a specific role
     */
    public function attachToTeam(Team $team, string $role = 'member'): void
    {
        if (!$this->belongsToTeam($team)) {
            $this->teams()->attach($team, ['role' => $role]);

            if (!$this->current_team_id) {
                $this->forceFill([
                    'current_team_id' => $team->id,
                ])->save();
            }
        }
    }

    /**
     * Get the workspaces owned by the user.
     */
    public function workspaces()
    {
        return $this->hasMany(\App\Models\Workspace::class, 'owner_user_id');
    }
}
