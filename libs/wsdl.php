<?php

declare(strict_types=1);

namespace ONVIF;

class WSDL
{
    public const Management = 'ver10/device/wsdl/devicemgmt.wsdl';
    public const Media = 'ver10/media/wsdl/media.wsdl';
    public const Event = 'ver10/events/wsdl/event.wsdl';
    public const Media2 = 'ver20/media/wsdl/media.wsdl';
    public const PTZ = 'ver20/ptz/wsdl/ptz.wsdl';
    public const DeviceIO = 'ver10/deviceio.wsdl';
    public const Imaging = 'ver20/imaging/wsdl/imaging.wsdl';
    public const Analytics = 'ver20/analytics/wsdl/analytics.wsdl';
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
class NS
{
    public const Management = 'http://www.onvif.org/ver10/device/wsdl';
    public const Media = 'http://www.onvif.org/ver10/media/wsdl';
    public const Event = 'http://www.onvif.org/ver10/events/wsdl';
    public const Media2 = 'http://www.onvif.org/ver20/media/wsdl';
    public const PTZ = 'http://www.onvif.org/ver20/ptz/wsdl';
    public const DeviceIO = 'http://www.onvif.org/ver10/deviceIO/wsdl';
    public const Imaging = 'http://www.onvif.org/ver20/imaging/wsdl';
    public const Analytics = 'http://www.onvif.org/ver20/analytics/wsdl';
    // all other NS
    public static $Namespaces = [
        's'      => 'http://www.w3.org/2003/05/soap-envelope',
        'e'      => 'http://www.w3.org/2003/05/soap-encoding',
        'wsa'    => 'http://www.w3.org/2005/08/addressing',
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
        'tds'    => 'http://www.onvif.org/ver10/device/wsdl',
        'trt'    => 'http://www.onvif.org/ver10/media/wsdl',
        'tev'    => 'http://www.onvif.org/ver10/events/wsdl',
        'timg'   => 'http://www.onvif.org/ver20/imaging/wsdl',
        'tst'    => 'http://www.onvif.org/ver10/storage/wsdl',
        'dn'     => 'http://www.onvif.org/ver10/network/wsdl',
        'tr2'    => 'http://www.onvif.org/ver20/media/wsdl',
        'tptz'   => 'http://www.onvif.org/ver20/ptz/wsdl',
        'tan'    => 'http://www.onvif.org/ver20/analytics/wsdl',
        'axt'    => 'http://www.onvif.org/ver20/analytics',
        'tmd'    => 'http://www.onvif.org/ver10/deviceIO/wsdl',
        'ter'    => 'http://www.onvif.org/ver10/error'
    ];
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
    public const All = 'All';
    public const VideoSource = 'VideoSource';
    public const VideoEncoder = 'VideoEncoder';
    public const AudioSource = 'AudioSource';
    public const AudioEncoder = 'AudioEncoder';
    public const AudioOutput = 'AudioOutput';
    public const AudioDecoder = 'AudioDecoder';
    public const Metadata = 'Metadata';
    public const Analytics = 'Analytics';
    public const PTZ = 'PTZ';
    public const Receiver = 'Receiver';
}

class Scopes
{
    public const ProfileT = 'onvif://www.onvif.org/Profile/T';
    public const ProfileG = 'onvif://www.onvif.org/Profile/G';
    public const ProfileS = 'onvif://www.onvif.org/Profile/Streaming';
}

class Profile
{
    public const S = 1; // Streaming und WS-Event
    public const G = 2; // Recording (ohne streaming!)
    public const T = 4; // Streaming und pull point Event, Image Settings

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