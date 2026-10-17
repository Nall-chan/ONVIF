<?php

declare(strict_types=1);

/**
 * Generator für libs/WSDL
 *
 * Holt die WSDL- und XSD-Dateien eines Release-Tags aus https://github.com/onvif/specs (Ordner `wsdl/`),
 * übernimmt davon nur die Dateien, die von den Konstanten in \ONVIF\WSDL aus erreichbar sind,
 * und passt sie für den PHP SoapClient (ext/soap) an. Die Anpassungen sind feste Regeln (siehe Rule).
 *
 * Aufruf (aus dem Hauptordner der Library):
 *   php tools/update-wsdl.php <Tag>                 libs/WSDL und tests/wsdl-snapshot aus dem Tag erzeugen
 *   php tools/update-wsdl.php --check               prüfen, ob libs/WSDL dem Tag aus VERSION.json entspricht
 *
 * Optionen:
 *   --source=<Verzeichnis>     vorhandener Checkout von onvif/specs statt git clone
 *   --target=<Verzeichnis>     anderes Zielverzeichnis statt libs/WSDL
 *   --snapshots=<Verzeichnis>  anderes Verzeichnis für die Snapshots statt tests/wsdl-snapshot
 *   --prune                    Dateien im Ziel löschen, die weder erzeugt noch fest eingecheckt sind
 *
 * Die Dateien unterliegen der Lizenz der jeweiligen Herausgeber (ONVIF, OASIS, W3C); die Kopfzeilen
 * mit Copyright und Lizenz bleiben beim Anpassen erhalten.
 */

namespace ONVIF\Tools;

require_once __DIR__ . '/WsdlSnapshot.php';
require_once dirname(__DIR__) . '/libs/wsdl.php';

/**
 * Namensräume für XPath und neu erzeugte Elemente
 */
class XmlNS
{
    public const Wsdl = 'http://schemas.xmlsoap.org/wsdl/';
    public const Soap12 = 'http://schemas.xmlsoap.org/wsdl/soap12/';
    public const Xsd = 'http://www.w3.org/2001/XMLSchema';
    public const Xmlns = 'http://www.w3.org/2000/xmlns/';
}

/**
 * Anpassungen an den Upstream-Dateien
 */
class Rule
{
    public const AddService = 'P1';
    public const LocalImports = 'P2';
    public const RemoveServiceImport = 'P3';
    public const EventNamespaces = 'P4';
    public const EventPullPointSupport = 'P5';
    public const CopyImportedElements = 'P6';

    public const Descriptions = [
        self::AddService            => 'wsdl:service/wsdl:port with empty soap:address added for every SOAP 1.2 binding (ext/soap requires a service, the address is set at runtime by the option location)',
        self::LocalImports          => 'Remote imports (OASIS, W3C) redirected to local copies',
        self::RemoveServiceImport   => 'wsdl:import of another ONVIF service WSDL removed (ext/soap: <message> already defined)',
        self::EventNamespaces       => 'event.wsdl: imports for tt and tns1 added, some devices send events with a different namespace',
        self::EventPullPointSupport => 'event.wsdl: attribute WSPullPointSupport added to tev:Capabilities, it was removed upstream but is evaluated by the IO',
        self::CopyImportedElements  => 'Elements of a WSDL removed by P3 that are used by wsdl:part (e.g. tds:GetRelayOutputs in deviceio.wsdl) copied into an own xs:schema, otherwise ext/soap sends an empty body',
    ];
}

/**
 * Erzeugt die angepassten Dateien im Speicher
 */
class WsdlGenerator
{
    public const Repository = 'https://github.com/onvif/specs.git';
    public const RepositoryUrl = 'https://github.com/onvif/specs';
    public const VersionFile = 'VERSION.json';

    /**
     * Externe Adressen in den Upstream-Dateien => lokale Datei (relativ zu libs/WSDL)
     */
    public const RemoteToLocal = [
        'http://docs.oasis-open.org/wsn/bw-2.wsdl'               => 'ver10/schema/bw-2.wsdl',
        'http://docs.oasis-open.org/wsrf/rw-2.wsdl'              => 'ver10/schema/rw-2.wsdl',
        'http://docs.oasis-open.org/wsn/b-2.xsd'                 => 'ver10/schema/b-2.xsd',
        'http://docs.oasis-open.org/wsn/t-1.xsd'                 => 'ver10/schema/t-1.xsd',
        'http://www.w3.org/2005/08/addressing/ws-addr.xsd'       => 'ver10/schema/ws-addr.xsd',
        'https://www.w3.org/2003/05/soap-envelope'               => 'ver10/schema/soap-envelope.xsd',
        'https://www.w3.org/2004/08/xop/include'                 => 'ver10/schema/include.xsd',
        'https://www.w3.org/2005/05/xmlmime'                     => 'ver10/schema/xmlmime.xsd',
        'http://www.onvif.org/ver10/schema/onvif.xsd'            => 'ver10/schema/onvif.xsd',
        'http://www.onvif.org/onvif/ver10/schema/onvif.xsd'      => 'ver10/schema/onvif.xsd',
    ];

    /**
     * Fest eingecheckte Schemas von OASIS und W3C (nicht Teil von onvif/specs, werden nicht erzeugt)
     */
    public const ExternalFiles = [
        'ver10/schema/b-2.xsd',
        'ver10/schema/bf-2.xsd',
        'ver10/schema/bw-2.wsdl',
        'ver10/schema/include.xsd',
        'ver10/schema/r-2.xsd',
        'ver10/schema/rw-2.wsdl',
        'ver10/schema/soap-envelope.xsd',
        'ver10/schema/t-1.xsd',
        'ver10/schema/ws-addr.xsd',
        'ver10/schema/xml.xsd',
        'ver10/schema/xmlmime.xsd',
    ];

    private const OnvifSchema = 'ver10/schema/onvif.xsd';

    /** @var array<string, string> Pfad => Inhalt */
    private array $Files = [];
    /** @var array<string, string[]> Pfad => angewendete Regeln */
    private array $Rules = [];

    /**
     * @param string $SourceDir Ordner `wsdl` eines Checkouts von onvif/specs
     */
    public function __construct(private string $SourceDir)
    {
    }

    /**
     * Verarbeitet alle Dateien, die von den Einstiegspunkten aus erreichbar sind.
     *
     * @param string[] $Entries Pfade relativ zu libs/WSDL (Werte von \ONVIF\WSDL)
     */
    public function generate(array $Entries): void
    {
        $Queue = array_values($Entries);
        $Done = [];
        while (count($Queue)) {
            $Path = array_shift($Queue);
            if (isset($Done[$Path])) {
                continue;
            }
            $Done[$Path] = true;
            if (in_array($Path, self::ExternalFiles, true)) {
                continue;
            }
            foreach ($this->processFile($Path) as $Dependency) {
                $Queue[] = $Dependency;
            }
        }
        ksort($this->Files, SORT_STRING);
    }

    /**
     * @return array<string, string> Pfad => Inhalt
     */
    public function files(): array
    {
        return $this->Files;
    }

    /**
     * @return array<string, string[]> Pfad => angewendete Regeln
     */
    public function rules(): array
    {
        return $this->Rules;
    }

    /**
     * Wandelt einen relativen Verweis aus $From in einen Pfad relativ zu libs/WSDL.
     */
    public static function resolve(string $From, string $Location): string
    {
        $Parts = [];
        $Directory = dirname($From);
        $Segments = explode('/', ($Directory === '.' ? '' : $Directory . '/') . $Location);
        foreach ($Segments as $Segment) {
            if ($Segment === '' || $Segment === '.') {
                continue;
            }
            if ($Segment === '..') {
                if (!count($Parts)) {
                    throw new \RuntimeException('Reference leaves the WSDL directory: ' . $Location . ' in ' . $From);
                }
                array_pop($Parts);
                continue;
            }
            $Parts[] = $Segment;
        }
        return implode('/', $Parts);
    }

    /**
     * Relativer Verweis von der Datei $From auf die Datei $To (beide relativ zu libs/WSDL).
     */
    public static function relativePath(string $From, string $To): string
    {
        $FromParts = explode('/', dirname($From));
        if ($FromParts === ['.']) {
            $FromParts = [];
        }
        $ToParts = explode('/', $To);
        while (count($FromParts) && count($ToParts) > 1 && $FromParts[0] === $ToParts[0]) {
            array_shift($FromParts);
            array_shift($ToParts);
        }
        return str_repeat('../', count($FromParts)) . implode('/', $ToParts);
    }

    /**
     * Passt eine Datei an und liefert ihre lokalen Abhängigkeiten.
     *
     * @return string[]
     */
    private function processFile(string $Path): array
    {
        $Raw = $this->readSource($Path);
        $Dom = self::loadDom($Raw, $Path);
        $XPath = self::createXPath($Dom);
        $Applied = [];

        // P2: externe Imports auf lokale Kopien umbiegen
        foreach ($this->referenceAttributes($XPath) as $Attribute) {
            $Location = trim($Attribute->value);
            if (!preg_match('#^https?://#i', $Location)) {
                continue;
            }
            if (!isset(self::RemoteToLocal[$Location])) {
                throw new \RuntimeException('Unknown remote import in ' . $Path . ': ' . $Location . ' (add it to WsdlGenerator::RemoteToLocal)');
            }
            $Attribute->value = self::relativePath($Path, self::RemoteToLocal[$Location]);
            $Applied[Rule::LocalImports] = true;
        }

        // P3: Import einer anderen ONVIF-Dienst-WSDL entfernen
        foreach (iterator_to_array($XPath->query('/wsdl:definitions/wsdl:import')) as $Import) {
            /** @var \DOMElement $Import */
            $Target = self::resolve($Path, $Import->getAttribute('location'));
            if (in_array($Target, self::ExternalFiles, true) || !$this->isServiceWsdl($Target)) {
                continue;
            }
            $Namespace = $Import->getAttribute('namespace');
            $Comment = $Dom->createComment(' update-wsdl ' . Rule::RemoveServiceImport . ': wsdl:import of ' . $Target . ' removed ');
            $Import->parentNode->replaceChild($Comment, $Import);
            $Applied[Rule::RemoveServiceImport] = true;
            // P6: Elemente aus der entfernten WSDL, auf die Nachrichten dieser Datei verweisen, übernehmen
            if ($this->copyReferencedElements($Dom, $XPath, $Path, $Target, $Namespace)) {
                $Applied[Rule::CopyImportedElements] = true;
            }
        }

        if ($Path === \ONVIF\WSDL::Event) {
            $Schema = $XPath->query('/wsdl:definitions/wsdl:types/xs:schema[@targetNamespace="' . \ONVIF\NS::Event . '"]')->item(0);
            if (!$Schema instanceof \DOMElement) {
                throw new \RuntimeException('Schema ' . \ONVIF\NS::Event . ' not found in ' . $Path);
            }
            if ($this->addEventNamespaces($XPath, $Schema, $Path)) {
                $Applied[Rule::EventNamespaces] = true;
            }
            if ($this->addEventPullPointSupport($Dom, $XPath, $Schema)) {
                $Applied[Rule::EventPullPointSupport] = true;
            }
        }

        // P1: wsdl:service ergänzen
        if ($this->addServices($Dom, $XPath)) {
            $Applied[Rule::AddService] = true;
        }

        $Dependencies = [];
        foreach ($this->referenceAttributes($XPath) as $Attribute) {
            $Dependencies[] = self::resolve($Path, trim($Attribute->value));
        }

        if (count($Applied)) {
            $Content = $Dom->saveXML();
            if (str_contains($Raw, "\r\n")) {
                $Content = str_replace("\n", "\r\n", $Content);
            }
        } else {
            $Content = $Raw;
        }
        $this->Files[$Path] = $Content;
        $Rules = array_keys($Applied);
        sort($Rules, SORT_STRING);
        $this->Rules[$Path] = $Rules;
        return array_values(array_unique($Dependencies));
    }

    private function readSource(string $Path): string
    {
        $File = $this->SourceDir . '/' . $Path;
        if (!is_file($File)) {
            throw new \RuntimeException('File not found in onvif/specs: wsdl/' . $Path);
        }
        return (string) file_get_contents($File);
    }

    private static function loadDom(string $Raw, string $Path): \DOMDocument
    {
        $Dom = new \DOMDocument();
        $Dom->preserveWhiteSpace = true;
        if (!$Dom->loadXML($Raw, LIBXML_NONET)) {
            throw new \RuntimeException('Invalid XML: ' . $Path);
        }
        return $Dom;
    }

    private static function createXPath(\DOMDocument $Dom): \DOMXPath
    {
        $XPath = new \DOMXPath($Dom);
        $XPath->registerNamespace('wsdl', XmlNS::Wsdl);
        $XPath->registerNamespace('soap12', XmlNS::Soap12);
        $XPath->registerNamespace('xs', XmlNS::Xsd);
        return $XPath;
    }

    /**
     * Alle Attribute, über die eine Datei andere Dateien lädt.
     *
     * @return \DOMAttr[]
     */
    private function referenceAttributes(\DOMXPath $XPath): array
    {
        return iterator_to_array($XPath->query(
            '//xs:import/@schemaLocation | //xs:include/@schemaLocation | //xs:redefine/@schemaLocation | //wsdl:import/@location'
        ));
    }

    /**
     * true, wenn die Upstream-Datei ein eigenes Binding enthält (also ein ONVIF-Dienst ist).
     */
    private function isServiceWsdl(string $Path): bool
    {
        $XPath = self::createXPath(self::loadDom($this->readSource($Path), $Path));
        return $XPath->query('/wsdl:definitions/wsdl:binding')->length > 0;
    }

    /**
     * P6: Kopiert die Elemente (und davon abhängige Typen) des Namensraums $Namespace aus der WSDL $Source,
     * auf die wsdl:part dieser Datei verweisen, in ein eigenes xs:schema in wsdl:types.
     */
    private function copyReferencedElements(\DOMDocument $Dom, \DOMXPath $XPath, string $Path, string $Source, string $Namespace): bool
    {
        $Queue = [];
        foreach ($XPath->query('/wsdl:definitions/wsdl:message/wsdl:part[@element]') as $Part) {
            /** @var \DOMElement $Part */
            [$Prefix, $Name] = self::splitQName($Part->getAttribute('element'));
            if ($Part->lookupNamespaceURI($Prefix) === $Namespace) {
                $Queue[] = ['element', $Name];
            }
        }
        if (!count($Queue)) {
            return false;
        }
        $SourceXPath = self::createXPath(self::loadDom($this->readSource($Source), $Source));
        $SourceSchema = $SourceXPath->query('/wsdl:definitions/wsdl:types/xs:schema[@targetNamespace="' . $Namespace . '"]')->item(0);
        if (!$SourceSchema instanceof \DOMElement) {
            throw new \RuntimeException('Schema ' . $Namespace . ' not found in ' . $Source);
        }
        // Elemente und Typen dieses Namensraums rekursiv einsammeln, in der Reihenfolge der Quelle
        $Found = [];
        while (count($Queue)) {
            [$Kind, $Name] = array_shift($Queue);
            if (isset($Found[$Kind . ':' . $Name])) {
                continue;
            }
            $Query = ($Kind === 'element') ? 'xs:element[@name="' . $Name . '"]' : '*[(local-name()="complexType" or local-name()="simpleType") and @name="' . $Name . '"]';
            $Node = $SourceXPath->query($Query, $SourceSchema)->item(0);
            if (!$Node instanceof \DOMElement) {
                throw new \RuntimeException($Kind . ' ' . $Name . ' not found in ' . $Source);
            }
            $Found[$Kind . ':' . $Name] = $Node;
            foreach ($SourceXPath->query('descendant-or-self::*/@type | descendant-or-self::*/@base | descendant-or-self::xs:element/@ref', $Node) as $Attribute) {
                /** @var \DOMAttr $Attribute */
                [$Prefix, $Reference] = self::splitQName($Attribute->value);
                if ($Attribute->ownerElement->lookupNamespaceURI($Prefix) === $Namespace) {
                    $Queue[] = [($Attribute->name === 'ref') ? 'element' : 'type', $Reference];
                }
            }
        }
        $Nodes = array_values($Found);
        usort($Nodes, fn (\DOMElement $A, \DOMElement $B): int => $A->getLineNo() <=> $B->getLineNo());

        $Types = $XPath->query('/wsdl:definitions/wsdl:types')->item(0);
        if (!$Types instanceof \DOMElement) {
            throw new \RuntimeException('wsdl:types not found in ' . $Path);
        }
        $XsdPrefix = $SourceSchema->prefix ?: 'xs';
        $Before = ($Types->lastChild instanceof \DOMText) ? $Types->lastChild : null;
        $Schema = self::insertElement($Types, $Before, XmlNS::Xsd, $XsdPrefix . ':schema', [], "\t\t");
        // Präfixe in Attributwerten (z. B. tt:RelayOutput) brauchen dieselben Deklarationen wie in der Quelle
        foreach ($SourceXPath->query('namespace::*', $SourceSchema) as $NamespaceNode) {
            /** @var \DOMNameSpaceNode $NamespaceNode */
            if (($NamespaceNode->prefix === 'xml') || ($NamespaceNode->prefix === '') || ($NamespaceNode->prefix === $XsdPrefix)) {
                continue;
            }
            $Schema->setAttributeNS(XmlNS::Xmlns, 'xmlns:' . $NamespaceNode->prefix, $NamespaceNode->namespaceURI);
        }
        foreach (['targetNamespace', 'elementFormDefault', 'version'] as $Attribute) {
            if ($SourceSchema->hasAttribute($Attribute)) {
                $Schema->setAttribute($Attribute, $SourceSchema->getAttribute($Attribute));
            }
        }
        foreach ($SourceXPath->query('xs:import[@schemaLocation]', $SourceSchema) as $SourceImport) {
            /** @var \DOMElement $SourceImport */
            $Location = trim($SourceImport->getAttribute('schemaLocation'));
            $Location = isset(self::RemoteToLocal[$Location]) ? self::RemoteToLocal[$Location] : self::resolve($Source, $Location);
            self::insertElement($Schema, null, XmlNS::Xsd, $XsdPrefix . ':import', ['namespace' => $SourceImport->getAttribute('namespace'), 'schemaLocation' => self::relativePath($Path, $Location)], "\t\t\t");
        }
        $Schema->appendChild($Dom->createTextNode("\n\t\t\t"));
        $Schema->appendChild($Dom->createComment(' update-wsdl ' . Rule::CopyImportedElements . ': copied from ' . $Source . ' '));
        foreach ($Nodes as $Node) {
            $Schema->appendChild($Dom->createTextNode("\n\t\t\t"));
            $Schema->appendChild($Dom->importNode($Node, true));
        }
        self::closeElement($Schema, "\t\t");
        return true;
    }

    /**
     * @return string[] [Präfix, lokaler Name]
     */
    private static function splitQName(string $QName): array
    {
        $Parts = explode(':', trim($QName), 2);
        return count($Parts) === 2 ? $Parts : ['', $Parts[0]];
    }

    /**
     * P1: je SOAP-1.2-Binding ohne Port einen Service mit leerer Adresse ergänzen.
     */
    private function addServices(\DOMDocument $Dom, \DOMXPath $XPath): bool
    {
        $Root = $Dom->documentElement;
        if ($Root->namespaceURI !== XmlNS::Wsdl) {
            return false;
        }
        $WsdlPrefix = $Root->lookupPrefix(XmlNS::Wsdl);
        $SoapPrefix = $Root->lookupPrefix(XmlNS::Soap12);
        $TargetPrefix = $Root->lookupPrefix($Root->getAttribute('targetNamespace'));
        $Added = false;
        foreach (iterator_to_array($XPath->query('/wsdl:definitions/wsdl:binding[soap12:binding]')) as $Binding) {
            /** @var \DOMElement $Binding */
            if (($WsdlPrefix === null) || ($SoapPrefix === null) || ($TargetPrefix === null)) {
                throw new \RuntimeException('Missing namespace prefix for wsdl, soap12 or targetNamespace');
            }
            $Name = $Binding->getAttribute('name');
            $QualifiedBinding = $TargetPrefix . ':' . $Name;
            if ($XPath->query('/wsdl:definitions/wsdl:service/wsdl:port[@binding="' . $QualifiedBinding . '"]')->length > 0) {
                continue;
            }
            $Base = (string) preg_replace('/Binding$/', '', $Name);
            // vor dem Zeilenumbruch, der </wsdl:definitions> vorangeht
            $Before = ($Root->lastChild instanceof \DOMText) ? $Root->lastChild : null;
            $Service = self::insertElement($Root, $Before, XmlNS::Wsdl, $WsdlPrefix . ':service', ['name' => $Base . 'Service'], "\t");
            $Port = self::insertElement($Service, null, XmlNS::Wsdl, $WsdlPrefix . ':port', ['name' => $Base . 'Port', 'binding' => $QualifiedBinding], "\t\t");
            self::insertElement($Port, null, XmlNS::Soap12, $SoapPrefix . ':address', ['location' => ''], "\t\t\t");
            self::closeElement($Port, "\t\t");
            self::closeElement($Service, "\t");
            if ($Before === null) {
                $Root->appendChild($Dom->createTextNode("\n"));
            }
            $Added = true;
        }
        return $Added;
    }

    /**
     * P4: Imports für tt und tns1 im Schema der event.wsdl ergänzen.
     */
    private function addEventNamespaces(\DOMXPath $XPath, \DOMElement $Schema, string $Path): bool
    {
        $Changed = false;
        $XsdPrefix = $Schema->lookupPrefix(XmlNS::Xsd) ?? 'xs';
        $Location = self::relativePath($Path, self::OnvifSchema);
        foreach (['tt', 'tns1'] as $Prefix) {
            $Namespace = \ONVIF\NS::Namespaces[$Prefix];
            if ($Schema->lookupNamespaceURI($Prefix) !== $Namespace) {
                $Schema->setAttributeNS(XmlNS::Xmlns, 'xmlns:' . $Prefix, $Namespace);
                $Changed = true;
            }
            if ($XPath->query('xs:import[@namespace="' . $Namespace . '"]', $Schema)->length > 0) {
                continue;
            }
            $Imports = $XPath->query('xs:import', $Schema);
            if ($Imports->length) {
                $Reference = $Imports->item($Imports->length - 1);
                self::insertElement($Schema, $Reference->nextSibling, XmlNS::Xsd, $XsdPrefix . ':import', ['namespace' => $Namespace, 'schemaLocation' => $Location], self::indentOf($Reference));
            } else {
                self::insertElement($Schema, $Schema->firstChild, XmlNS::Xsd, $XsdPrefix . ':import', ['namespace' => $Namespace, 'schemaLocation' => $Location], self::indentOf($Schema) . "\t");
            }
            $Changed = true;
        }
        return $Changed;
    }

    /**
     * P5: Attribut WSPullPointSupport in tev:Capabilities ergänzen.
     */
    private function addEventPullPointSupport(\DOMDocument $Dom, \DOMXPath $XPath, \DOMElement $Schema): bool
    {
        $Capabilities = $XPath->query('xs:complexType[@name="Capabilities"]', $Schema)->item(0);
        if (!$Capabilities instanceof \DOMElement) {
            throw new \RuntimeException('Type tev:Capabilities not found');
        }
        if ($XPath->query('xs:attribute[@name="WSPullPointSupport"]', $Capabilities)->length > 0) {
            return false;
        }
        $Reference = $XPath->query('xs:attribute[@name="WSSubscriptionPolicySupport"]', $Capabilities)->item(0);
        if ($Reference === null) {
            $Attributes = $XPath->query('xs:attribute', $Capabilities);
            $Reference = $Attributes->length ? $Attributes->item($Attributes->length - 1) : null;
        }
        $Indent = $Reference ? self::indentOf($Reference) : self::indentOf($Capabilities) . "\t";
        $XsdPrefix = $Capabilities->lookupPrefix(XmlNS::Xsd) ?? 'xs';
        $Before = $Reference ? $Reference->nextSibling : (($Capabilities->lastChild instanceof \DOMText) ? $Capabilities->lastChild : null);
        $Attribute = self::insertElement($Capabilities, $Before, XmlNS::Xsd, $XsdPrefix . ':attribute', ['name' => 'WSPullPointSupport', 'type' => $XsdPrefix . ':boolean'], $Indent);
        $Annotation = self::insertElement($Attribute, null, XmlNS::Xsd, $XsdPrefix . ':annotation', [], $Indent . "\t");
        $Documentation = self::insertElement($Annotation, null, XmlNS::Xsd, $XsdPrefix . ':documentation', [], $Indent . "\t\t");
        $Documentation->appendChild($Dom->createTextNode('Indicates that the WS Pull Point is supported.'));
        self::closeElement($Annotation, $Indent . "\t");
        self::closeElement($Attribute, $Indent);
        return true;
    }

    /**
     * Fügt ein neues Element mit vorangestelltem Zeilenumbruch und Einrückung vor $Before ein (null = am Ende).
     *
     * Das Element wird direkt im Dokument eingefügt, bevor es Kinder bekommt. So löst das DOM die Präfixe
     * über die Deklarationen der Vorfahren auf und schreibt keine doppelten xmlns-Attribute.
     *
     * @param array<string, string> $Attributes
     */
    private static function insertElement(\DOMElement $Parent, ?\DOMNode $Before, string $Namespace, string $QualifiedName, array $Attributes, string $Indent): \DOMElement
    {
        $Dom = $Parent->ownerDocument;
        $Element = $Dom->createElementNS($Namespace, $QualifiedName);
        $Parent->insertBefore($Dom->createTextNode("\n" . $Indent), $Before);
        $Parent->insertBefore($Element, $Before);
        foreach ($Attributes as $Name => $Value) {
            $Element->setAttribute($Name, $Value);
        }
        return $Element;
    }

    /**
     * Schließt ein Element mit Kindelementen: Zeilenumbruch und Einrückung vor dem End-Tag.
     */
    private static function closeElement(\DOMElement $Element, string $Indent): void
    {
        $Element->appendChild($Element->ownerDocument->createTextNode("\n" . $Indent));
    }

    private static function indentOf(\DOMNode $Node): string
    {
        $Previous = $Node->previousSibling;
        if (!$Previous instanceof \DOMText) {
            return '';
        }
        $Text = str_replace("\r", '', $Previous->data);
        $Position = strrpos($Text, "\n");
        return $Position === false ? $Text : substr($Text, $Position + 1);
    }
}

/**
 * Kommandozeile: Upstream holen, erzeugen, prüfen oder schreiben
 */
class UpdateWsdl
{
    private string $Root;
    private string $Target;
    private string $Snapshots;
    private ?string $Source = null;
    private ?string $Tag = null;
    private bool $Check = false;
    private bool $Prune = false;
    /** @var string[] */
    private array $TempDirs = [];

    /**
     * @param string[] $Arguments
     */
    public function __construct(array $Arguments)
    {
        $this->Root = dirname(__DIR__);
        $this->Target = $this->Root . '/libs/WSDL';
        $this->Snapshots = $this->Root . '/' . WsdlSnapshot::Directory;
        foreach ($Arguments as $Argument) {
            if ($Argument === '--check') {
                $this->Check = true;
            } elseif ($Argument === '--prune') {
                $this->Prune = true;
            } elseif (str_starts_with($Argument, '--source=')) {
                $this->Source = rtrim(substr($Argument, 9), '/\\');
            } elseif (str_starts_with($Argument, '--target=')) {
                $this->Target = rtrim(substr($Argument, 9), '/\\');
            } elseif (str_starts_with($Argument, '--snapshots=')) {
                $this->Snapshots = rtrim(substr($Argument, 12), '/\\');
            } elseif (!str_starts_with($Argument, '--') && $this->Tag === null) {
                $this->Tag = $Argument;
            } else {
                throw new \InvalidArgumentException('Unknown argument: ' . $Argument);
            }
        }
    }

    public function __destruct()
    {
        foreach ($this->TempDirs as $Dir) {
            self::removeDirectory($Dir);
        }
    }

    public function run(): int
    {
        if ($this->Tag === null && $this->Check) {
            $this->Tag = $this->readVersion()['Tag'] ?? null;
        }
        if ($this->Tag === null || !preg_match('/^\d{2}\.\d{2}$/', $this->Tag)) {
            fwrite(STDERR, 'Usage: php tools/update-wsdl.php <Tag, e.g. 26.06> [--source=<dir>] [--target=<dir>] [--snapshots=<dir>] [--prune] | --check' . "\n");
            return 2;
        }
        $SpecsDir = $this->Source ?? $this->fetchUpstream($this->Tag);
        [$Commit, $CommitDate] = self::commitInfo($SpecsDir);
        echo 'onvif/specs ' . $this->Tag . ' (' . $Commit . ', ' . $CommitDate . ')' . "\n";

        $Entries = array_values((new \ReflectionClass(\ONVIF\WSDL::class))->getConstants());
        $Generator = new WsdlGenerator($SpecsDir . '/wsdl');
        $Generator->generate($Entries);

        // Staging: erzeugte Dateien + fest eingecheckte Fremdschemas
        $Staging = $this->createTempDir('stage');
        foreach ($Generator->files() as $Path => $Content) {
            self::writeFile($Staging . '/' . $Path, $Content);
        }
        foreach (WsdlGenerator::ExternalFiles as $Path) {
            $External = $this->Root . '/libs/WSDL/' . $Path;
            if (!is_file($External)) {
                throw new \RuntimeException('External schema missing: libs/WSDL/' . $Path);
            }
            self::writeFile($Staging . '/' . $Path, (string) file_get_contents($External));
        }
        $Version = $this->versionInfo($Generator, $Commit, $CommitDate);
        self::writeFile($Staging . '/' . WsdlGenerator::VersionFile, self::encodeJson($Version));

        // Snapshots aus dem Staging
        $SnapshotFiles = [];
        foreach ((new \ReflectionClass(\ONVIF\WSDL::class))->getConstants() as $Name => $Path) {
            $SnapshotFiles[WsdlSnapshot::fileName($Name)] = WsdlSnapshot::create($Staging . '/' . $Path);
        }

        foreach ($Generator->rules() as $Path => $Rules) {
            echo '  ' . str_pad($Path, 42) . (count($Rules) ? implode(', ', $Rules) : '-') . "\n";
        }

        $StagedFiles = [WsdlGenerator::VersionFile => true];
        foreach (array_merge(array_keys($Generator->files()), WsdlGenerator::ExternalFiles) as $Path) {
            $StagedFiles[$Path] = true;
        }
        $Extra = array_values(array_filter(self::listFiles($this->Target), fn (string $Path): bool => !isset($StagedFiles[$Path])));

        if ($this->Check) {
            return $this->check($Staging, array_keys($StagedFiles), $Extra, $SnapshotFiles);
        }

        foreach (array_keys($StagedFiles) as $Path) {
            self::writeFile($this->Target . '/' . $Path, (string) file_get_contents($Staging . '/' . $Path));
        }
        foreach ($SnapshotFiles as $File => $Content) {
            self::writeFile($this->Snapshots . '/' . $File, $Content);
        }
        foreach ($Extra as $Path) {
            if ($this->Prune) {
                unlink($this->Target . '/' . $Path);
                echo 'Removed: ' . $Path . "\n";
            } else {
                echo 'Not generated (use --prune to remove): ' . $Path . "\n";
            }
        }
        echo 'Written: ' . count($StagedFiles) . ' files, ' . count($SnapshotFiles) . ' snapshots' . "\n";
        return 0;
    }

    /**
     * Vergleicht Staging und Snapshots mit dem Ziel. Zeilenenden werden dabei ignoriert.
     *
     * @param string[]              $Paths
     * @param string[]              $Extra
     * @param array<string, string> $SnapshotFiles
     */
    private function check(string $Staging, array $Paths, array $Extra, array $SnapshotFiles): int
    {
        $Differences = [];
        foreach ($Paths as $Path) {
            $Current = $this->Target . '/' . $Path;
            if (!is_file($Current)) {
                $Differences[] = 'missing: libs/WSDL/' . $Path;
            } elseif (self::normalize((string) file_get_contents($Current)) !== self::normalize((string) file_get_contents($Staging . '/' . $Path))) {
                $Differences[] = 'changed: libs/WSDL/' . $Path;
            }
        }
        foreach ($Extra as $Path) {
            $Differences[] = 'not generated: libs/WSDL/' . $Path;
        }
        foreach ($SnapshotFiles as $File => $Content) {
            $Current = $this->Snapshots . '/' . $File;
            if (!is_file($Current) || self::normalize((string) file_get_contents($Current)) !== self::normalize($Content)) {
                $Differences[] = 'snapshot differs: ' . WsdlSnapshot::Directory . '/' . $File;
            }
        }
        if (count($Differences)) {
            echo implode("\n", $Differences) . "\n" . 'Check failed: ' . count($Differences) . ' difference(s)' . "\n";
            return 1;
        }
        echo 'Check passed' . "\n";
        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function versionInfo(WsdlGenerator $Generator, string $Commit, string $CommitDate): array
    {
        return [
            'Source'     => WsdlGenerator::RepositoryUrl,
            'Tag'        => $this->Tag,
            'Commit'     => $Commit,
            'CommitDate' => $CommitDate,
            'Generator'  => 'tools/update-wsdl.php',
            'Rules'      => Rule::Descriptions,
            'Files'      => $Generator->rules(),
            'External'   => WsdlGenerator::ExternalFiles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readVersion(): array
    {
        $File = $this->Target . '/' . WsdlGenerator::VersionFile;
        if (!is_file($File)) {
            return [];
        }
        return json_decode((string) file_get_contents($File), true) ?? [];
    }

    private function fetchUpstream(string $Tag): string
    {
        $Dir = $this->createTempDir('specs');
        self::runCommand(['git', 'clone', '--quiet', '--config', 'core.autocrlf=false', '--depth', '1', '--branch', $Tag, '--filter=blob:none', '--sparse', WsdlGenerator::Repository, $Dir]);
        self::runCommand(['git', '-C', $Dir, 'sparse-checkout', 'set', 'wsdl']);
        return $Dir;
    }

    /**
     * @return string[] [Commit, Datum]
     */
    private static function commitInfo(string $Dir): array
    {
        if (!file_exists($Dir . '/.git')) {
            return ['unknown', 'unknown'];
        }
        return [
            trim(self::runCommand(['git', '-C', $Dir, 'rev-parse', 'HEAD'])),
            trim(self::runCommand(['git', '-C', $Dir, 'log', '-1', '--format=%cs']))
        ];
    }

    /**
     * @param string[] $Command
     */
    private static function runCommand(array $Command): string
    {
        // Argument-Array ohne Shell: escapeshellarg ersetzt unter Windows z. B. % durch ein Leerzeichen
        $Process = proc_open($Command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $Pipes);
        if (!is_resource($Process)) {
            throw new \RuntimeException('Command could not be started: ' . implode(' ', $Command));
        }
        $Output = (string) stream_get_contents($Pipes[1]);
        $Error = (string) stream_get_contents($Pipes[2]);
        fclose($Pipes[1]);
        fclose($Pipes[2]);
        $ExitCode = proc_close($Process);
        if ($ExitCode !== 0) {
            throw new \RuntimeException('Command failed (' . $ExitCode . '): ' . implode(' ', $Command) . "\n" . $Output . $Error);
        }
        return $Output;
    }

    private function createTempDir(string $Name): string
    {
        $Dir = sys_get_temp_dir() . '/update-wsdl-' . $Name . '-' . bin2hex(random_bytes(4));
        mkdir($Dir, 0777, true);
        $this->TempDirs[] = $Dir;
        return $Dir;
    }

    /**
     * @return string[] Pfade relativ zu $Dir
     */
    private static function listFiles(string $Dir): array
    {
        if (!is_dir($Dir)) {
            return [];
        }
        $Files = [];
        $Iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($Dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($Iterator as $File) {
            /** @var \SplFileInfo $File */
            $Files[] = str_replace('\\', '/', substr($File->getPathname(), strlen($Dir) + 1));
        }
        sort($Files, SORT_STRING);
        return $Files;
    }

    private static function writeFile(string $File, string $Content): void
    {
        if (!is_dir(dirname($File))) {
            mkdir(dirname($File), 0777, true);
        }
        file_put_contents($File, $Content);
    }

    private static function removeDirectory(string $Dir): void
    {
        if (!is_dir($Dir)) {
            return;
        }
        $Iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($Dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($Iterator as $File) {
            /** @var \SplFileInfo $File */
            @chmod($File->getPathname(), 0777); // git legt Objekte schreibgeschützt an
            $File->isDir() ? rmdir($File->getPathname()) : unlink($File->getPathname());
        }
        rmdir($Dir);
    }

    private static function normalize(string $Content): string
    {
        return str_replace("\r\n", "\n", $Content);
    }

    /**
     * @param array<string, mixed> $Data
     */
    private static function encodeJson(array $Data): string
    {
        return json_encode($Data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $Update = new UpdateWsdl(array_slice($argv, 1));
        $ExitCode = $Update->run();
        unset($Update);
        exit($ExitCode);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
