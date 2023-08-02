<?php

declare(strict_types=1);

namespace ONVIF;

/**
 * WSDL Files
 */
class WSDL
{
    const Management = 'ver10/device/wsdl/devicemgmt.wsdl';
    const Media = 'ver10/media/wsdl/media.wsdl';
    const Event = 'ver10/events/wsdl/event.wsdl';
    const Media2 = 'ver20/media/wsdl/media.wsdl';
    const PTZ = 'ver20/ptz/wsdl/ptz.wsdl';
    const DeviceIO = 'ver10/deviceio.wsdl';
    const Imaging = 'ver20/imaging/wsdl/imaging.wsdl';
    const Analytics = 'ver20/analytics/wsdl/analytics.wsdl';
    public static $getWSDL = [
        self::Management => NS::Management,
        self::Media      => NS::Media,
        self::Media2     => NS::Media2,
        self::Event      => NS::Event,
        self::PTZ        => NS::PTZ,
        self::DeviceIO   => NS::DeviceIO,
        self::Imaging    => NS::Imaging,
        self::Analytics  => NS::Analytics
    ];
}
/**
 * Namespaces ONVIF, SOAP & XML
 */
class NS
{
    const Management = 'http://www.onvif.org/ver10/device/wsdl';
    const Media = 'http://www.onvif.org/ver10/media/wsdl';
    const Event = 'http://www.onvif.org/ver10/events/wsdl';
    const Media2 = 'http://www.onvif.org/ver20/media/wsdl';
    const PTZ = 'http://www.onvif.org/ver20/ptz/wsdl';
    const DeviceIO = 'http://www.onvif.org/ver10/deviceIO/wsdl';
    const Imaging = 'http://www.onvif.org/ver20/imaging/wsdl';
    const Analytics = 'http://www.onvif.org/ver20/analytics/wsdl';
    const Addressing = 'http://www.w3.org/2005/08/addressing';
    // all other NS
    public static $Namespaces = [
        's'      => 'http://www.w3.org/2003/05/soap-envelope',
        'e'      => 'http://www.w3.org/2003/05/soap-encoding',
        'wsa'    => self::Addressing,
        'xs'     => 'http://www.w3.org/2001/XMLSchema',
        'xsi'    => 'http://www.w3.org/2001/XMLSchema-instance',
        'wsaw'   => 'http://www.w3.org/2006/05/addressing/wsdl',
        'wsnt'   => 'http://docs.oasis-open.org/wsn/b-2',
        'wstop'  => 'http://docs.oasis-open.org/wsn/t-1',
        'wsntw'  => 'http://docs.oasis-open.org/wsn/bw-2',
        'wsrf-rw'=> 'http://docs.oasis-open.org/wsrf/rw-2',
        'wsrf-r' => 'http://docs.oasis-open.org/wsrf/r-2',
        'wsrf-bf'=> 'http://docs.oasis-open.org/wsrf/bf-2',
        'wsdl'   => 'http://schemas.xmlsoap.org/wsdl',
        'wsoap12'=> 'http://schemas.xmlsoap.org/wsdl/soap12',
        'http'   => 'http://schemas.xmlsoap.org/wsdl/http',
        'd'      => 'http://schemas.xmlsoap.org/ws/2005/04/discovery',
        'wsadis' => 'http://schemas.xmlsoap.org/ws/2004/08/addressing',
        'tt'     => 'http://www.onvif.org/ver10/schema',
        'tns1'   => 'http://www.onvif.org/ver10/topics',
        'tds'    => self::Management,
        'trt'    => self::Media,
        'tev'    => self::Event,
        'timg'   => self::Imaging,
        'tst'    => 'http://www.onvif.org/ver10/storage/wsdl',
        'dn'     => 'http://www.onvif.org/ver10/network/wsdl',
        'tr2'    => self::Media2,
        'tptz'   => self::PTZ,
        'tan'    => self::Analytics,
        'axt'    => 'http://www.onvif.org/ver20/analytics',
        'tmd'    => self::DeviceIO,
        'ter'    => 'http://www.onvif.org/ver10/error'
    ];
    /**
     * Namespaces to Files
     */
    public static $getWSDL = [
        self::Management => WSDL::Management,
        self::Media      => WSDL::Media,
        self::Media2     => WSDL::Media2,
        self::Event      => WSDL::Event,
        self::PTZ        => WSDL::PTZ,
        self::DeviceIO   => WSDL::DeviceIO,
        self::Imaging    => WSDL::Imaging,
        self::Analytics  => WSDL::Analytics
    ];
}

class Media2Conf
{
    const All = 'All';
    const VideoSource = 'VideoSource';
    const VideoEncoder = 'VideoEncoder';
    const AudioSource = 'AudioSource';
    const AudioEncoder = 'AudioEncoder';
    const AudioOutput = 'AudioOutput';
    const AudioDecoder = 'AudioDecoder';
    const Metadata = 'Metadata';
    const Analytics = 'Analytics';
    const PTZ = 'PTZ';
    const Receiver = 'Receiver';
}

class Scopes
{
    const ProfileT = 'onvif://www.onvif.org/Profile/T';
    const ProfileG = 'onvif://www.onvif.org/Profile/G';
    const ProfileS = 'onvif://www.onvif.org/Profile/Streaming';
}

class EventHandler
{
    const None = 0;
    const Subscribe = 1;
    const PullPoint = 2;
    const Automatic = 3;
    public int $Type;
    public function __construct(int $Type = 0)
    {
        $this->Type = $Type;
    }
    public function __sleep()
    {
        return ['Type'];
    }
    public function toString(): string
    {
        switch($this->Type) {
            case self::Subscribe:
                return 'Subscription';
            case self::PullPoint:
                return 'PullPoint';
            case self::Automatic:
                return 'Automatic';
        }
        return 'none';
    }
}

class Profile
{
    const S = 1; // Streaming und WS-Event
    const G = 2; // Recording (ohne streaming!)
    const T = 4; // Streaming und pull point Event, Image Settings

    public static $ScopesToProfile = [
        Scopes::ProfileS         => self::S,
        Scopes::ProfileG         => self::G,
        Scopes::ProfileT         => self::T
    ];
    public static $ProfileBitToChar = [
        self::S => 'S',
        self::G => 'G',
        self::T => 'T'
    ];
    public $Profile;
    public function __construct(array $Scopes = [])
    {
        $this->Profile = 0;
        foreach ($Scopes as $Scope) {
            if (array_key_exists($Scope, self::$ScopesToProfile)) {
                $this->Profile |= self::$ScopesToProfile[$Scope];
            }
        }
    }
    public function __sleep()
    {
        return ['Profile'];
    }
    public function HasProfile(int $Profile)
    {
        return ($this->Profile & $Profile) == $Profile;
    }
    public function toString(): string
    {
        $Profiles = [];
        foreach (self::$ProfileBitToChar as $Bit => $Char) {
            if ($this->HasProfile($Bit)) {
                $Profiles[] = $Char;
            }
        }
        return implode(', ', $Profiles);
    }
}
