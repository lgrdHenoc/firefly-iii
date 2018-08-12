<?php
/**
 * GetConfigurationData.php
 * Copyright (c) 2018 thegrumpydictator@gmail.com
 *
 * This file is part of Firefly III.
 *
 * Firefly III is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Firefly III is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Firefly III. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FireflyIII\Support\Http\Controllers;


use Carbon\Carbon;
use Log;

/**
 * Trait GetConfigurationData
 *
 */
trait GetConfigurationData
{
    /**
     * All packages that are installed.
     *
     * @return array
     */
    protected function collectPackages(): array  // get configuration
    {
        $packages = [];
        $file     = \dirname(__DIR__, 4) . '/vendor/composer/installed.json';
        if (file_exists($file)) {
            // file exists!
            $content = file_get_contents($file);
            $json    = json_decode($content, true);
            foreach ($json as $package) {
                $packages[]
                    = [
                    'name'    => $package['name'],
                    'version' => $package['version'],
                ];
            }
        }

        return $packages;
    }

    /**
     * Some common combinations.
     *
     * @param int $value
     *
     * @return string
     */
    protected function errorReporting(int $value): string // get configuration
    {
        $array  = [
            -1                                                             => 'ALL errors',
            E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED                  => 'E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED',
            E_ALL                                                          => 'E_ALL',
            E_ALL & ~E_DEPRECATED & ~E_STRICT                              => 'E_ALL & ~E_DEPRECATED & ~E_STRICT',
            E_ALL & ~E_NOTICE                                              => 'E_ALL & ~E_NOTICE',
            E_ALL & ~E_NOTICE & ~E_STRICT                                  => 'E_ALL & ~E_NOTICE & ~E_STRICT',
            E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_ERROR | E_CORE_ERROR => 'E_COMPILE_ERROR|E_RECOVERABLE_ERROR|E_ERROR|E_CORE_ERROR',
        ];
        $result = (string)$value;
        if (isset($array[$value])) {
            $result = $array[$value];
        }

        return $result;
    }

    /**
     * Get the basic steps from config.
     *
     * @param string $route
     *
     * @return array
     */
    protected function getBasicSteps(string $route): array // get config values
    {
        $routeKey = str_replace('.', '_', $route);
        $elements = config(sprintf('intro.%s', $routeKey));
        $steps    = [];
        if (\is_array($elements) && \count($elements) > 0) {
            foreach ($elements as $key => $options) {
                $currentStep = $options;

                // get the text:
                $currentStep['intro'] = (string)trans('intro.' . $route . '_' . $key);

                // save in array:
                $steps[] = $currentStep;
            }
        }
        Log::debug(sprintf('Total basic steps for %s is %d', $routeKey, \count($steps)));

        return $steps;
    }

    /**
     * Get config for date range.
     *
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function getDateRangeConfig(): array // get configuration + get preferences.
    {
        $viewRange = app('preferences')->get('viewRange', '1M')->data;
        /** @var Carbon $start */
        $start = session('start');
        /** @var Carbon $end */
        $end = session('end');
        /** @var Carbon $first */
        $first    = session('first');
        $title    = sprintf('%s - %s', $start->formatLocalized($this->monthAndDayFormat), $end->formatLocalized($this->monthAndDayFormat));
        $isCustom = true === session('is_custom_range', false);
        $today    = new Carbon;
        $ranges   = [
            // first range is the current range:
            $title => [$start, $end],
        ];
        Log::debug(sprintf('viewRange is %s', $viewRange));
        Log::debug(sprintf('isCustom is %s', var_export($isCustom, true)));

        // when current range is a custom range, add the current period as the next range.
        if ($isCustom) {
            Log::debug('Custom is true.');
            $index             = app('navigation')->periodShow($start, $viewRange);
            $customPeriodStart = app('navigation')->startOfPeriod($start, $viewRange);
            $customPeriodEnd   = app('navigation')->endOfPeriod($customPeriodStart, $viewRange);
            $ranges[$index]    = [$customPeriodStart, $customPeriodEnd];
        }
        // then add previous range and next range
        $previousDate   = app('navigation')->subtractPeriod($start, $viewRange);
        $index          = app('navigation')->periodShow($previousDate, $viewRange);
        $previousStart  = app('navigation')->startOfPeriod($previousDate, $viewRange);
        $previousEnd    = app('navigation')->endOfPeriod($previousStart, $viewRange);
        $ranges[$index] = [$previousStart, $previousEnd];

        $nextDate       = app('navigation')->addPeriod($start, $viewRange, 0);
        $index          = app('navigation')->periodShow($nextDate, $viewRange);
        $nextStart      = app('navigation')->startOfPeriod($nextDate, $viewRange);
        $nextEnd        = app('navigation')->endOfPeriod($nextStart, $viewRange);
        $ranges[$index] = [$nextStart, $nextEnd];

        // today:
        /** @var Carbon $todayStart */
        $todayStart = app('navigation')->startOfPeriod($today, $viewRange);
        /** @var Carbon $todayEnd */
        $todayEnd = app('navigation')->endOfPeriod($todayStart, $viewRange);
        if ($todayStart->ne($start) || $todayEnd->ne($end)) {
            $ranges[ucfirst((string)trans('firefly.today'))] = [$todayStart, $todayEnd];
        }

        // everything
        $index          = (string)trans('firefly.everything');
        $ranges[$index] = [$first, new Carbon];

        $return = [
            'title'         => $title,
            'configuration' => [
                'apply'       => (string)trans('firefly.apply'),
                'cancel'      => (string)trans('firefly.cancel'),
                'from'        => (string)trans('firefly.from'),
                'to'          => (string)trans('firefly.to'),
                'customRange' => (string)trans('firefly.customRange'),
                'start'       => $start->format('Y-m-d'),
                'end'         => $end->format('Y-m-d'),
                'ranges'      => $ranges,
            ],
        ];

        return $return;
    }

    /**
     * Get specific info for special routes.
     *
     * @param string $route
     * @param string $specificPage
     *
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function getSpecificSteps(string $route, string $specificPage): array // get config values
    {
        $steps    = [];
        $routeKey = '';

        // user is on page with specific instructions:
        if (\strlen($specificPage) > 0) {
            $routeKey = str_replace('.', '_', $route);
            $elements = config(sprintf('intro.%s', $routeKey . '_' . $specificPage));
            if (\is_array($elements) && \count($elements) > 0) {
                foreach ($elements as $key => $options) {
                    $currentStep = $options;

                    // get the text:
                    $currentStep['intro'] = (string)trans('intro.' . $route . '_' . $specificPage . '_' . $key);

                    // save in array:
                    $steps[] = $currentStep;
                }
            }
        }
        Log::debug(sprintf('Total specific steps for route "%s" and page "%s" (routeKey is "%s") is %d', $route, $specificPage, $routeKey, \count($steps)));

        return $steps;
    }

    /**
     * Check if forbidden functions are set.
     *
     * @return bool
     */
    protected function hasForbiddenFunctions(): bool // validate system config
    {
        $list      = ['proc_close'];
        $forbidden = explode(',', ini_get('disable_functions'));
        $trimmed   = array_map(
            function (string $value) {
                return trim($value);
            }, $forbidden
        );
        foreach ($list as $entry) {
            if (\in_array($entry, $trimmed, true)) {
                Log::error('Method "%s" is FORBIDDEN, so the console command cannot be executed.');

                return true;
            }
        }

        return false;
    }
}