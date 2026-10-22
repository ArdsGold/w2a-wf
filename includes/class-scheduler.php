<?php
/** Calendar scheduling in the WordPress timezone, anchored to the start day. */
if (!defined('ABSPATH')) exit;

class WTAI_Scheduler {
    /** Read-only preview uses the same calculation as importing, including overflow and DST. */
    public static function preview($settings, $count) {
        $dates = self::calculate($settings, $count);
        if (is_wp_error($dates)) return $dates;
        $sample = array();
        $positions = array_unique(array_merge(range(0, min(4, $count - 1)), array($count - 1)));
        $unique = array();
        $publish_now = 0;
        foreach ($dates as $index => $date) {
            $key = $date ? $date->format('Y-m-d H:i:s') : 'immediate';
            $unique[$key] = true;
            if (self::status($date) === 'publish') $publish_now++;
            if (in_array($index, $positions, true)) $sample[] = array('article' => $index + 1, 'date' => $date ? $date->format('Y-m-d') : '', 'label' => $date ? wp_date('D, j M Y', $date->getTimestamp(), wp_timezone()) : __('Publish immediately', 'wolf-forge-article-generator'));
        }
        return array('dates' => $sample, 'total' => $count, 'repeated' => $dates[0] === null ? 0 : $count - count($unique), 'publish_now' => $publish_now, 'timezone' => wp_timezone()->getName());
    }

    public static function calculate($settings, $count) {
        if ($count < 1 || $count > 1000) {
            return new WP_Error('wtai_schedule_error', __('Select between 1 and 1,000 documents.', 'wolf-forge-article-generator'));
        }
        if ($settings['publish_schedule'] === 'immediate') return array_fill(0, $count, null);

        try {
            if ($settings['publish_schedule'] === 'specific_month') {
                $month = $settings['schedule_month'] ?? '';
                $start = DateTime::createFromFormat('!Y-m', $month, wp_timezone());
                if (!$start || $start->format('Y-m') !== $month) throw new Exception(__('Please select a valid month and year.', 'wolf-forge-article-generator'));
                $start->setTime(9, 0);
                $days = (int) $start->format('t');
                $dates = array();
                for ($index = 0; $index < $count; $index++) {
                    $offset = $count > $days ? $index % $days : ($count > 1 ? (int) round($index * ($days - 1) / ($count - 1)) : 0);
                    $date = clone $start;
                    $date->modify('+' . $offset . ' days');
                    $dates[] = $date;
                }
                return $dates;
            }
            $start = self::parse_date($settings['schedule_start_date']);
            if (!$start) throw new Exception(__('Please select a valid start date.', 'wolf-forge-article-generator'));
            $start->setTime(9, 0);
            $end = null;
            if ($settings['publish_schedule'] === 'custom_range') {
                $end = self::parse_date($settings['schedule_end_date']);
                if (!$end) throw new Exception(__('Please select a valid end date.', 'wolf-forge-article-generator'));
                $end->setTime(23, 59, 59);
                if ($end < $start) throw new Exception(__('End date must not be before the start date.', 'wolf-forge-article-generator'));
            }

            $dates = array();
            for ($index = 0; $index < $count; $index++) {
                $date = clone $start;
                switch ($settings['schedule_interval']) {
                    case 'month':
                        // Jan 31 -> Feb 28/29 -> Mar 31, without PHP's month overflow.
                        $date->modify('first day of this month');
                        $date->modify('+' . $index . ' months');
                        $date->setDate((int) $date->format('Y'), (int) $date->format('m'), min((int) $start->format('d'), (int) $date->format('t')));
                        break;
                    case 'week':
                        $date->modify('+' . ($index * 7) . ' days');
                        break;
                    default:
                        $date->modify('+' . $index . ' days');
                }
                if ($end && $date > $end) {
                    $slots = count($dates);
                    while (count($dates) < $count) $dates[] = clone $dates[count($dates) % $slots];
                    break;
                }
                $dates[] = $date;
            }
            return $dates;
        } catch (Throwable $error) {
            return new WP_Error('wtai_schedule_error', $error->getMessage());
        }
    }

    private static function parse_date($value) {
        $date = DateTime::createFromFormat('!Y-m-d', $value, wp_timezone());
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    public static function status($date) {
        return $date instanceof DateTime && $date > new DateTime('now', wp_timezone()) ? 'future' : 'publish';
    }
}
