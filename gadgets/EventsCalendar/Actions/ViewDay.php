<?php
/**
 * EventsCalendar Gadget
 *
 * @category    Gadget
 * @package     EventsCalendar
 * @author      Mohsen Khahani <mkhahani@gmail.com>
 * @copyright   2013 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
$GLOBALS['app']->Layout->AddHeadLink('gadgets/EventsCalendar/Resources/site_style.css');
class EventsCalendar_Actions_ViewDay extends Jaws_Gadget_Action
{
    /**
     * Builds day view UI
     *
     * @access  public
     * @return  string  XHTML UI
     */
    function ViewDay()
    {
        $data = jaws()->request->fetch(array('year', 'month', 'day'), 'get');
        $year = (int)$data['year'];
        $month = (int)$data['month'];
        $day = (int)$data['day'];

        $this->AjaxMe('site_script.js');
        $tpl = $this->gadget->loadTemplate('ViewDay.html');
        $tpl->SetBlock('day');

        $tpl->SetVariable('lbl_hour', _t('EVENTSCALENDAR_HOUR'));
        $tpl->SetVariable('lbl_events', _t('EVENTSCALENDAR_EVENTS'));

        // Menubar
        $action = $this->gadget->loadAction('Menubar');
        $tpl->SetVariable('menubar', $action->Menubar('Events'));

        $jdate = $GLOBALS['app']->loadDate();

        // Previous day
        $date = $jdate->ToBaseDate($year, $month, $day - 1);
        $tpl->SetVariable('prev', $jdate->Format($date['timestamp'], 'DN d MN Y'));
        $info = $jdate->GetDateInfo($year, $month, $day - 1);
        $url = $this->gadget->urlMap('ViewDay', array(
            'year' => $info['year'],
            'month' => $info['mon'],
            'day' => $info['mday']
        ));
        $tpl->SetVariable('prev_url', $url);

        // Next day
        $date = $jdate->ToBaseDate($year, $month, $day + 1);
        $tpl->SetVariable('next', $jdate->Format($date['timestamp'], 'DN d MN Y'));
        $info = $jdate->GetDateInfo($year, $month, $day + 1);
        $url = $this->gadget->urlMap('ViewDay', array(
            'year' => $info['year'],
            'month' => $info['mon'],
            'day' => $info['mday']
        ));
        $tpl->SetVariable('next_url', $url);

        // Today
        $date = $jdate->ToBaseDate($year, $month, $day);
        $today = $jdate->Format($date['timestamp'], 'DN d MN Y');
        $tpl->SetVariable('title', $today);
        $this->SetTitle($today . ' - ' . _t('EVENTSCALENDAR_EVENTS'));

        // Repeat
        $info = $jdate->GetDateInfo($year, $month, $day);
        $repeat = array();
        $repeat['day'] = $day;
        $repeat['wday'] = $info['wday'] + 1;
        $repeat['month'] = $month;

        // Fetch events
        $model = $this->gadget->loadModel('Report');
        $user = (int)$GLOBALS['app']->Session->GetAttribute('user');
        $start = $date['timestamp'];
        $stop = $jdate->ToBaseDate($year, $month, $day, 23, 59, 59);
        $stop = $stop['timestamp'];
        $events = $model->GetEvents($user, null, null, $start, $stop, $repeat);
        if (Jaws_Error::IsError($events)){
            $events = array();
        }

        // Prepare events
        $eventsById = array();
        $eventsByHour = array_fill(0, 24, array());
        foreach ($events as $e) {
            $eventsById[$e['id']] = $e;
            $startIdx = floor($e['start_time'] / 3600);
            $stopIdx = floor($e['stop_time'] / 3600);
            for ($i = $startIdx; $i <= $stopIdx; $i++) {
                $eventsByHour[$i][] = $e['id'];
            }
        }

        // Display events
        for ($i = 0; $i <= 23; $i++) {
            $time = date('H:00', mktime($i));
            $tpl->SetBlock('day/hour');
            $tpl->SetVariable('hour', $time);
            foreach ($eventsByHour[$i] as $event_id) {
                $tpl->SetBlock('day/hour/event');
                $tpl->SetVariable('event', $eventsById[$event_id]['subject']);
                $tpl->ParseBlock('day/hour/event');
            }
            $tpl->ParseBlock('day/hour');
        }

        $tpl->ParseBlock('day');
        return $tpl->Get();
    }
}