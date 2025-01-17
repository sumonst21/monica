<?php

namespace App\Http\Controllers\DAV\Backend\CalDAV;

use Illuminate\Support\Facades\Log;
use App\Models\Instance\SpecialDate;
use Illuminate\Support\Facades\Auth;
use Sabre\DAV\Server as SabreServer;
use Sabre\CalDAV\Plugin as CalDAVPlugin;
use App\Services\VCalendar\ExportVCalendar;
use Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;

class CalDAVBirthdays extends AbstractCalDAVBackend
{
    /**
     * Returns the uri for this backend.
     *
     * @return string
     */
    public function backendUri()
    {
        return 'birthdays';
    }

    public function getDescription()
    {
        return parent::getDescription()
        + [
            '{DAV:}displayname' => trans('app.dav_birthdays'),
            '{'.SabreServer::NS_SABREDAV.'}read-only' => true,
            '{'.CalDAVPlugin::NS_CALDAV.'}calendar-description' => trans('app.dav_birthdays_description', ['name' => Auth::user()->name]),
            '{'.CalDAVPlugin::NS_CALDAV.'}calendar-timezone' => Auth::user()->timezone,
            '{'.CalDAVPlugin::NS_CALDAV.'}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
            '{'.CalDAVPlugin::NS_CALDAV.'}schedule-calendar-transp' => new ScheduleCalendarTransp(ScheduleCalendarTransp::TRANSPARENT),
        ];
    }

    /**
     * Extension for Calendar objects.
     *
     * @return string
     */
    public function getExtension()
    {
        return '.ics';
    }

    /**
     * Datas for this date.
     *
     * @param  mixed  $obj
     * @return array
     */
    public function prepareData($obj)
    {
        if ($obj instanceof SpecialDate) {
            try {
                $vcal = app(ExportVCalendar::class)
                    ->execute([
                        'account_id' => Auth::user()->account_id,
                        'special_date_id' => $obj->id,
                    ]);

                $calendardata = $vcal->serialize();

                return [
                    'id' => $obj->id,
                    'uri' => $this->encodeUri($obj),
                    'calendardata' => $calendardata,
                    'etag' => '"'.md5($calendardata).'"',
                    'lastmodified' => $obj->updated_at->timestamp,
                ];
            } catch (\Exception $e) {
                Log::debug(__CLASS__.' prepareData: '.(string) $e);
            }
        }

        return [];
    }

    private function hasBirthday($contact)
    {
        if (! $contact || ! $contact->birthdate) {
            return false;
        }
        $birthdayState = $contact->getBirthdayState();
        if ($birthdayState != 'almost' && $birthdayState != 'exact') {
            return false;
        }

        return true;
    }

    /**
     * Returns the date for the specific uuid.
     *
     * @param  string|null  $collectionId
     * @param  string  $uuid
     * @return mixed
     */
    public function getObjectUuid($collectionId, $uuid)
    {
        return SpecialDate::where([
            'account_id' => Auth::user()->account_id,
            'uuid' => $uuid,
        ])->first();
    }

    /**
     * Returns the collection of contact's birthdays.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getObjects($collectionId)
    {
        // We only return the birthday of default addressBook
        $contacts = Auth::user()->account->contacts()
                    ->real()
                    ->active()
                    ->get();

        return $contacts->filter(function ($contact) {
            return $this->hasBirthday($contact);
        })
        ->map(function ($contact) {
            return $contact->birthdate;
        });
    }

    /**
     * @return string|null
     */
    public function updateOrCreateCalendarObject($calendarId, $objectUri, $calendarData): ?string
    {
        return null;
    }

    public function deleteCalendarObject($objectUri)
    {
    }
}
