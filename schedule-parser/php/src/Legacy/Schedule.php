<?php

declare(strict_types=1);

namespace Src\Legacy;

use Smalot\PdfParser\Document;

/**
 * @deprecated Was actual for 1 trimester of 2024-2025 year
 */
final class Schedule
{
    private const WEEK_DAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Sunday',
        'Saturday'
    ];
    private const LESSON_TYPE_PRACTICE = 'practice';
    private const LESSON_TYPE_LECTURE = 'lecture';

    public function parsePdfScedule(Document $schedule): array
    {
        $lines = explode("\n", $schedule->getText());
        $groups = [];

        $currentGroup = null;
        $currentWeekDay = null;
        $prevIndex = null;
        $prevSeq = false;

        for ($I = 0; $I < count($lines); $I++) {

            $line = $lines[$I];
            if (strlen(trim($line)) < 1) {
                continue;
            }

            $line = preg_replace('/[ ]{2,}|[\t]/', ' ', trim($line));
            if (str_contains($line, "Group ")) {
                $currentGroup = mb_strtoupper(explode(" ", $line)[1]);
                $currentWeekDay = null;
                $prevIndex = null;
                continue;
            }

            foreach (self::WEEK_DAYS as $weekDay) {
                if (str_contains($line, $weekDay) && count(explode(" ", $line)) === 1) {
                    $currentWeekDay = $weekDay;
                    continue 2;
                }
            }

            if ($currentWeekDay === null) {
                continue;
            }

            $index = $this->extractTime($line);
            if ($index === false && $prevIndex === null) {
                continue;
            }

            if ($index === false && $prevIndex !== null) {
                $elemsL = [
                    'time' => $prevIndex,
                    'items' => []
                ];
                $elemsL['items'][] = $groups[$currentGroup][$currentWeekDay][$prevIndex];
                $localI = $I;
                $cc = 0;
                $groups[$currentGroup][$currentWeekDay][$prevIndex] = [];
                while ($this->extractTime($lines[$localI]) === false) {
                    $localLine = trim($lines[$localI]);
                    if (strlen(($localLine)) < 1) {
                        $localI++;
                        continue;
                    }
                    if ($localLine[0] === '*') {
                        break;
                    }
                    $localLine = preg_replace('/[ ]{2,}|[\t]/', ' ', trim($localLine));

                    if ($localLine === self::LESSON_TYPE_LECTURE || $localLine === self::LESSON_TYPE_PRACTICE) {
                        $cc++;
                    }
                    $elemsL['items'][] = $localLine;
                    $localI++;
                }

                if ($cc === 0) {
                    $groups[$currentGroup][$currentWeekDay][$prevIndex] = trim(implode(" ", $elemsL['items']));
                } else {
                    for ($j = 0; $j < $cc; $j++) {
                        $groups[$currentGroup][$currentWeekDay][$prevIndex][] = trim(
                            trim($elemsL['items'][0 + ($j)]) . ' ' .
                            trim($elemsL['items'][(1 * $cc) + ($j)]) . ' ' .
                            trim($elemsL['items'][(2 * $cc) + ($j)]) . ' ' .
                            trim($elemsL['items'][(3 * $cc) + ($j)])
                        );
                    }
                }
                $prevSeq = !$prevSeq;
                $I = $localI;
                $prevIndex = null;
                continue;
            }

            if ($index !== false) {
                $prevSeq = false;
            }

            if ($currentGroup !== null && trim($line) !== '' && $index !== false) {
                $prevIndex = $index;
                $groups[$currentGroup][$currentWeekDay][$index] = trim(substr($line, 11));
                $index = false;
            }
        }

        foreach ($groups as $group => $weekRasp) {
            foreach ($weekRasp as $weekDay => $dayRasp) {
                foreach ($dayRasp as $time => $value) {
                    if (is_string($value)) {
                        $groups[$group][$weekDay][$time] = (object)$this->extractDataFromString($value);
                        continue;
                    }

                    if (is_array($value)) {
                        $groups[$group][$weekDay][$time] = [];
                        foreach ($value as $valueStr) {
                            $groups[$group][$weekDay][$time][] = (object)$this->extractDataFromString($valueStr);
                        }
                    }
                }
            }
        }

        return $this->format($groups);
    }

    private function format(array $groups): array
    {
        foreach ($groups as $group => $weekRasp) {
            $res[$group] = [];
            foreach ($weekRasp as $weekDay => $dayRasp) {
                foreach ($dayRasp as $time => $value) {
                    $times = explode('-', $time);
                    if (is_object($value)) {
                        $value = (array)$value;
                        $res[$group][] = [
                            'id' => uniqid(),
                            'start' => "$weekDay " . $times[0],
                            'end' => "$weekDay " . $times[1],
                            'courseName' => $value['courseName'],
                            'location' => $value['cabinet'],
                            'isOnline' => $value['isOnline'],
                            'teacher' => $value['teacher'],
                            'type' => $value['type'],
                        ];
                        continue;
                    }

                    if (is_array($value)) {
                        foreach ($value as $v) {
                            $v = (array)$v;
                            $res[$group][] = [
                                'id' => uniqid(),
                                'start' => "$weekDay " . $times[0],
                                'end' => "$weekDay " . $times[1],
                                'courseName' => $v['courseName'],
                                'location' => $v['cabinet'],
                                'isOnline' => $v['isOnline'],
                                'teacher' => $v['teacher'],
                                'type' => $v['type'],
                            ];
                        }
                        continue;
                    }
                }
            }
        }

        return $res;
    }

    private function extractTime(string $line): string|false
    {
        if (strlen($line) < 12) {
            return false;
        }
        if (is_numeric($line[0]) && is_numeric($line[1])
            && ($line[2] === ':')
            && is_numeric($line[3]) && is_numeric($line[4])
            && ($line[5] === '-')
            && is_numeric($line[6]) && is_numeric($line[7])
            && ($line[8] === ':')
            && is_numeric($line[9]) && is_numeric($line[10])
        ) {
            return substr($line, 0, 11);
        }

        return false;
    }

    private function extractDataFromString(string $data): array
    {
        $dataArr = explode(' ', $data);

        $stopCourseName = false;
        $stopCabinet = true;
        $stopPrepod = true;

        $courseName = [];
        $cabinet = [];
        $isOnline = false;
        $prepod = [];
        $type = null;

        foreach ($dataArr as $key => $dataStr) {
            if ($key === 0) {
                $courseName[] = $dataStr;
                continue;
            }

            if (($dataStr[0] === 'C' && $dataStr[1] === '1' && $dataStr[2] === '.') || ($dataStr === 'gym') || ($dataStr === 'online') || (str_contains($dataStr, 'IEC-'))) {
                if ($dataStr === 'online') {
                    $isOnline = true;
                }
                $stopCabinet = false;
                $stopCourseName = true;
            }

            if ($dataStr === 'lecture' || $dataStr === 'practice') {
                $type = $dataStr;
                $stopCabinet = true;
                $stopPrepod = false;
                continue;
            }

            if ($stopCourseName === false) {
                $courseName[] = $dataStr;
            }

            if ($stopCabinet === false) {
                $cabinet[] = $dataStr;
            }

            if ($stopPrepod === false) {
                $prepod[] = $dataStr;
            }
        }

        return [
            'courseName' => implode(' ', $courseName),
            'cabinet' => implode(' ', $cabinet),
            'teacher' => implode(' ', $prepod),
            'isOnline' => $isOnline,
            'type' => $type
        ];
    }
}
