<?php

declare(strict_types=1);

namespace ONVIF
{
    /**
     * WSDL Files
     */
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
    }
    /**
     * Namespaces ONVIF, SOAP & XML
     */
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
        public const Addressing = 'http://www.w3.org/2005/08/addressing';
        // all other NS
        public const Namespaces = [
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

    class EventHandler
    {
        public const None = 0;
        public const Subscribe = 1;
        public const PullPoint = 2;
        public const Automatic = 3;
        public int $Type;
        public function __construct(int $Type = 0)
        {
            $this->Type = $Type;
        }
        public function __sleep(): array
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
        public const NONE = 1; // Fallback Profil S
        public const S = 2; // Streaming und WS-Event
        public const G = 4; // Recording (ohne streaming!)
        public const T = 8; // Streaming und pull point Event, Image Settings

        private const ScopesToProfile = [
            Scopes::ProfileS         => self::S,
            Scopes::ProfileG         => self::G,
            Scopes::ProfileT         => self::T
        ];
        private const ProfileBitToChar = [
            self::NONE => 'Fallback S',
            self::S    => 'S',
            self::G    => 'G',
            self::T    => 'T'
        ];
        public int $Profile;
        public function __construct(array $Scopes = [])
        {
            $this->Profile = 0;
            foreach ($Scopes as $Scope) {
                if (array_key_exists($Scope, self::ScopesToProfile)) {
                    $this->Profile |= self::ScopesToProfile[$Scope];
                }
            }
        }
        public function __sleep(): array
        {
            return ['Profile'];
        }
        public function HasProfile(int $Profile): bool
        {
            return ($this->Profile & $Profile) == $Profile;
        }
        public function toString(): string
        {
            $Profiles = [];
            foreach (self::ProfileBitToChar as $Bit => $Char) {
                if ($this->HasProfile($Bit)) {
                    $Profiles[] = $Char;
                }
            }
            return implode(', ', $Profiles);
        }
    }
    class GUID
    {
        public const Configurator = '{C6A79C49-19D5-8D45-FFE5-5D77165FAEE6}';
        public const IO = '{F40CA9A7-3B4D-4B26-7214-3A94B6074DFB}';
        public const Input = '{73097230-1ECC-FEEB-5969-C85148DFA76E}';
        public const Output = '{A44B3114-1F72-1FD1-96FB-D7E970BD8614}';
        public const Stream = '{FA889450-38B6-7E20-D4DC-F2C6D0B074FB}';
        public const ImageGrabber = '{18EA97C1-3CEC-80B7-4CAA-D91F8A2A0599}';
        public const Event = '{62584C2E-4542-4EBF-1E92-299F4CF364E4}';
    }
}

namespace ONVIF\Discovery
{
    class Attribute
    {
        public const Username = 'Username';
        public const Password = 'Password';
    }
}

namespace ONVIF\IO
{
    class Property
    {
        public const Active = 'Open';
        public const Address = 'Address';
        public const Username = 'Username';
        public const Password = 'Password';
        public const EventHandler = 'EventHandler';
        public const WebHookIP = 'WebHookIP';
        public const WebHookHTTPS = 'WebHookHTTPS';
        public const WebHookPort = 'WebHookPort';
        public const SubscribeEventTimeout = 'SubscribeEventTimeout';
        public const SubscribeInitialTerminationTime = 'SubscribeInitialTerminationTime';
        public const PullPointInitialTerminationTime = 'PullPointInitialTerminationTime';
        public const PullPointTimeout = 'PullPointTimeout';
        public const MessageLimit = 'MessageLimit';
    }
    class Attribute
    {
        public const VideoSources = 'VideoSources';
        public const AudioSources = 'AudioSources';
        public const VideoSourcesJPEG = 'VideoSourcesJPEG';
        public const AnalyticsTokens = 'AnalyticsTokens';
        public const RelayOutputs = 'RelayOutputs';
        public const DigitalInputs = 'DigitalInputs';
        public const Timestamp_Offset = 'Timestamp_Offset';
        public const XAddr = 'XAddr';
        public const EventProperties = 'EventProperties';
        public const NbrOfInputs = 'NbrOfInputs';
        public const NbrOfOutputs = 'NbrOfOutputs';
        public const NbrOfVideoSources = 'NbrOfVideoSources';
        public const NbrOfAudioSources = 'NbrOfAudioSources';
        public const NbrOfSerialPorts = 'NbrOfSerialPorts';
        public const HasSnapshotUri = 'HasSnapshotUri';
        public const HasRTSPStreaming = 'HasRTSPStreaming';
        public const RuleSupport = 'RuleSupport';
        public const AnalyticsModuleSupport = 'AnalyticsModuleSupport';
        public const WSSubscriptionPolicySupport = 'WSSubscriptionPolicySupport';
        public const WSPullPointSupport = 'WSPullPointSupport';
        public const ConsumerAddress = 'ConsumerAddress';
        public const SubscriptionReference = 'SubscriptionReference';
        public const SubscriptionId = 'SubscriptionId';
        public const CapabilitiesVersion = 'CapabilitiesVersion';
    }
    class Timer
    {
        public const RenewSubscription = 'RenewSubscription';
    }
    class State
    {
        public const INACTIVE = 'Interface closed';
        public const ACTIVE = 'Interface connected';
        public const CONNECTION_LOST = 'Connection lost';
    }
}

namespace ONVIF\DataFlow{
    class GUID
    {
        public const SendEvents = '{E23DD2CD-F098-268A-CE49-1CC04FE8060B}';
        public const SendFunction = '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}';
    }
}

namespace ONVIF\Device
{
    class Property
    {
        public const EventTopic = 'EventTopic';
    }
    class Attribute
    {
        public const EventProperties = 'EventProperties';
    }
}

namespace ONVIF\Stream
{
    class Property
    {
        public const VideoSource = 'VideoSource';
        public const Profile = 'Profile';
        public const InvertPanControl = 'InvertPanControl';
        public const InvertTiltControl = 'InvertTiltControl';
        public const InvertZoomControl = 'InvertZoomControl';
        public const EnablePanTiltVariable = 'EnablePanTiltVariable';
        public const EnableZoomVariable = 'EnableZoomVariable';
        public const EnableSpeedVariable = 'EnableSpeedVariable';
        public const EnableTimeVariable = 'EnableTimeVariable';
        public const EnablePanTiltHTML = 'EnablePanTiltHTML';
        public const EnableZoomHTML = 'EnableZoomHTML';
        public const PanTiltControlWidth = 'PanTiltControlWidth';
        public const PanTiltControlHeight = 'PanTiltControlHeight';
        public const PanTiltControlOpacity = 'PanTiltControlOpacity';
        public const ZoomControlWidth = 'ZoomControlWidth';
        public const ZoomControlHeight = 'ZoomControlHeight';
        public const ZoomControlOpacity = 'ZoomControlOpacity';
        public const PanDefaultSpeed = 'PanDefaultSpeed';
        public const TiltDefaultSpeed = 'TiltDefaultSpeed';
        public const ZoomDefaultSpeed = 'ZoomDefaultSpeed';
        public const EnablePresetVariable = 'EnablePresetVariable';
        public const EnablePresetProfile = 'EnablePresetProfile';
        public const PresetProfile = 'PresetProfile';
    }
}

namespace ONVIF\ImageGrabber
{
    class Property
    {
        public const VideoSource = 'VideoSource';
        public const Profile = 'Profile';
        public const Interval = 'Interval';
        public const UseCaching = 'UseCaching';
    }
    class Timer
    {
        public const UpdateImage = 'UpdateImage';
    }
}

namespace ONVIF\Input
{
    class Attribute
    {
        public const DigitalInputs = 'DigitalInputs';
    }
}

namespace ONVIF\Output
{
    class Property
    {
        public const EmulateStatus = 'EmulateStatus';
    }
    class Attribute
    {
        public const RelayOutputs = 'RelayOutputs';
    }
}