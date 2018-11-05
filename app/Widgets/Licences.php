<?php

namespace App\Widgets;

use App\Licence;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Widgets\BaseDimmer;

class Licences extends BaseDimmer
{
    /**
     * The configuration array.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Treat this method as a controller action.
     * Return view() or other content to display.
     */
    public function run()
    {
        $count = Licence::count();
        $string = 'Licences';

        return view('voyager::dimmer', array_merge($this->config, [
            'icon'   => 'voyager-key',
            'title'  => "{$count} {$string}",
            'text'   => __('You have '.$count.' licences in your database. Click on button below to view all licences.', ['count' => $count, 'string' => Str::lower($string)]),
            'button' => [
                'text' => __('View all licences'),
                'link' => route('voyager.licences.index'),
            ],
            'image' => asset('images/widgets/key_widget.jpg'),
        ]));
    }

    /**
     * Determine if the widget should be displayed.
     *
     * @return bool
     */
    public function shouldBeDisplayed()
    {
        return Voyager::can('browse_licences');
    }
}
