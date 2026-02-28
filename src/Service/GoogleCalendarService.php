<?php

namespace App\Service;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;

class GoogleCalendarService
{
    private $client;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig(__DIR__.'/../../config/credentials.json');
        $this->client->addScope(Google_Service_Calendar::CALENDAR);
        $this->client->setAccessType('offline');
    }

    public function getClient()
    {
        return $this->client;
    }

    public function createEvent($title, $description, $start, $end)
    {
        $service = new Google_Service_Calendar($this->client);

        $event = new Google_Service_Calendar_Event([
            'summary' => $title,
            'description' => $description,
            'start' => [
                'dateTime' => $start,
                'timeZone' => 'Africa/Tunis',
            ],
            'end' => [
                'dateTime' => $end,
                'timeZone' => 'Africa/Tunis',
            ],
        ]);

        $calendarId = 'primary';
        return $service->events->insert($calendarId, $event);
    }
}
