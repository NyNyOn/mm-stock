<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\User;

class UserProfilePicture extends Component
{
    public User $user;
    public string $sizeClass;
    public string $ringClasses;
    public bool $isCreator;

    /**
     * Create a new component instance.
     */
    public function __construct(User $user, string $size = 'md')
    {
        $this->user = $user;
        $this->isCreator = $user->id === (int)config('auth.super_admin_id');
        $this->sizeClass = $this->getSizeClass($size);
        $this->ringClasses = $this->getRingAndGlowClasses($user->getRoleLevel(), $this->isCreator);
    }

    /**
     * Get the CSS class for the component's size.
     */
    private function getSizeClass(string $size): string
    {
        return [
            'lg' => 'w-14 h-14',
            'md' => 'w-12 h-12',
            'sm' => 'w-10 h-10',
        ][$size] ?? 'w-12 h-12';
    }

    /**
     * Get the CSS classes for the ring and glow effect.
     */
    private function getRingAndGlowClasses(int $roleLevel, bool $isCreator): string
    {
        // If the user is the creator, apply a special pink glow effect.
        if ($isCreator) {
            return 'ring-2 ring-pink-300 shadow-lg shadow-pink-300/50';
        }

        // Other users get a standard colored ring based on their group level.
        if ($roleLevel >= 90) return 'ring-2 ring-purple-400'; // IT
        if ($roleLevel >= 50) return 'ring-2 ring-blue-400';   // Admin

        return 'ring-2 ring-green-400'; // User
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.user-profile-picture');
    }
}
