<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserWidgetPreference;
use Illuminate\Support\Str;

class WidgetVisibility
{
    /**
     * @param  array<class-string>  $availableWidgets
     * @return array<class-string>
     */
    public static function selected(User $user, string $panel, string $page, array $availableWidgets): array
    {
        $record = UserWidgetPreference::query()
            ->where('user_id', $user->id)
            ->where('panel', $panel)
            ->where('page', $page)
            ->first();

        $saved = is_array($record?->visible_widgets) ? $record->visible_widgets : null;

        if ($saved === null) {
            return $availableWidgets;
        }

        $allowed = array_flip($availableWidgets);
        $selected = [];

        foreach ($saved as $widgetClass) {
            if (is_string($widgetClass) && isset($allowed[$widgetClass])) {
                $selected[] = $widgetClass;
            }
        }

        return $selected === [] ? $availableWidgets : $selected;
    }

    /**
     * @param  array<class-string>  $availableWidgets
     * @param  array<int, string>   $selectedWidgets
     */
    public static function save(User $user, string $panel, string $page, array $availableWidgets, array $selectedWidgets): void
    {
        $allowed = array_flip($availableWidgets);
        $filtered = [];

        foreach ($selectedWidgets as $widgetClass) {
            if (is_string($widgetClass) && isset($allowed[$widgetClass])) {
                $filtered[] = $widgetClass;
            }
        }

        UserWidgetPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'panel' => $panel,
                'page' => $page,
            ],
            [
                'visible_widgets' => $filtered,
            ],
        );
    }

    /**
     * @param  array<class-string>  $widgets
     * @return array<string, string>
     */
    public static function options(array $widgets): array
    {
        $options = [];

        foreach ($widgets as $widgetClass) {
            $base = class_basename($widgetClass);
            $label = (string) Str::of($base)->replace('Widget', '')->headline();
            $options[$widgetClass] = __($label);
        }

        return $options;
    }
}

