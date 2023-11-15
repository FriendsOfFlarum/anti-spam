<?php

namespace FoF\AntiSpam\Filters;

use FoF\AntiSpam\Concerns;
use FoF\AntiSpam\Filter;
use Flarum\Post\Event\Saving;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Validation\Concerns\ValidatesAttributes;

class Discussion
{
    use Concerns\Approval,
        Concerns\Content,
        Concerns\Users,
        Concerns\SpamBlock,
        ValidatesAttributes;

    public function subscribe(Dispatcher $events)
    {
        // This class is disabled, skip.
        if (in_array(static::class, Filter::$disabled)) return;

        $events->listen(Saving::class, [$this, 'filter']);
    }

    public function filter(Saving $event)
    {
        if ($event->post->number == null || $event->post->number !== 1) return;

        $discussion = $event->post->discussion;
        $firstPost = $event->post;

        // Disallow any blocked content and any urls in subject.
        $badContent = $this->containsProblematicContent($discussion->title)
            || $this->validateUrl('url', $discussion->title);

        if ($badContent
            // Ignore discussions that are soft deleted (already).
            && $discussion->hidden_at === null
            // Ignore any elevated user.
            && ! $this->isElevatedUser($event->actor)
            // Only enact spam prevent on fresh users.
            && $this->isFreshUser($discussion->user)) {

            $discussion->afterSave(function (\Flarum\Discussion\Discussion $discussion) use ($firstPost) {
                // Try to mark as spammer
                $this->markAsSpammer($discussion->user)
                // otherwise mark for approval
                || $this->unapproveAndFlag($firstPost, 'Discussion subject contains bad content.');
            });
        }
    }
}
