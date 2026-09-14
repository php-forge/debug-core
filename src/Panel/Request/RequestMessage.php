<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

/**
 * Presentation text of the Request panel, shared by every adapter that renders it.
 */
enum RequestMessage: string
{
    /**
     * Overview field label of the dispatched action, also used as a routing entry label.
     */
    case ACTION = 'Action';

    /**
     * Heading of the server group mirroring the content headers.
     */
    case ADDITIONAL_HEADER_VARIABLES = 'Additional header variables';

    /**
     * Flag label of an AJAX request.
     */
    case AJAX = 'AJAX';

    /**
     * Value shown for a route definition that constrains no method or host.
     */
    case ANY = 'Any';

    /**
     * Field label of the request body content type.
     */
    case CONTENT_TYPE = 'Content Type';

    /**
     * Caption of the cookie section.
     */
    case COOKIES = 'Cookies';

    /**
     * Field label of the decoded request body.
     */
    case DECODED = 'Decoded';

    /**
     * Overview field label of the request duration.
     */
    case DURATION = 'Duration';

    /**
     * Heading of the server group carrying the variables no other group claims.
     */
    case ENVIRONMENT_OTHER = 'Environment & other';

    /**
     * Caption of the file upload section.
     */
    case FILES = 'Files';

    /**
     * Prefix of the accessible label of a server group filter, completed with the group name.
     */
    case FILTER_PREFIX = 'Filter ';

    /**
     * Flag label of a request carrying flash messages.
     */
    case FLASH = 'Flash';

    /**
     * Caption of the flash message section.
     */
    case FLASHES = 'Flashes';

    /**
     * Caption of the query parameter section.
     */
    case GET = 'Get';

    /**
     * Heading of the header exchange disclosure.
     */
    case HEADER_EXCHANGE = 'Header exchange';

    /**
     * Heading of the server group mirroring the request headers.
     */
    case HEADER_MIRRORS = 'Header mirrors';

    /**
     * Navigation label of the headers tab, also the overview field label of the header section.
     */
    case HEADERS = 'Headers';

    /**
     * Accessible label of the header exchange filter.
     */
    case HEADERS_FILTER = 'Filter request and response headers';

    /**
     * Route definition label of the host constraints.
     */
    case HOSTS = 'Hosts';

    /**
     * Flag label of a request served over TLS.
     */
    case HTTPS = 'HTTPS';

    /**
     * Direction of a captured request header.
     */
    case INBOUND = 'Inbound';

    /**
     * Navigation label of the input tab.
     */
    case INPUT = 'Input';

    /**
     * Overview field label of the client address.
     */
    case IP = 'IP';

    /**
     * Badge shown when routing matched a rule.
     */
    case MATCHED = 'Matched';

    /**
     * Route definition label of the method constraints.
     */
    case METHODS = 'Methods';

    /**
     * Route definition label of the middleware stack.
     */
    case MIDDLEWARE = 'Middleware';

    /**
     * Header of the variable name column.
     */
    case NAME = 'Name';

    /**
     * Heading of the server group carrying the network and transport variables.
     */
    case NETWORK_TRANSPORT = 'Network & transport';

    /**
     * Note shown when a section carried no captured entry.
     */
    case NO_DATA = 'No data';

    /**
     * Value shown for a route definition that declares no middleware.
     */
    case NONE = 'None';

    /**
     * Direction of a captured response header.
     */
    case OUTBOUND = 'Outbound';

    /**
     * Navigation label of the parameters tab, also the routing entry label of the action parameters.
     */
    case PARAMETERS = 'Parameters';

    /**
     * Route definition label of the URL pattern.
     */
    case PATTERN = 'Pattern';

    /**
     * Flag label of a PJAX request.
     */
    case PJAX = 'PJAX';

    /**
     * Caption of the body parameter section.
     */
    case POST = 'Post';

    /**
     * Prefix of the disclosure label of a raw header line, completed with the header name.
     */
    case RAW_HEADER_LINE = 'Raw header line ';

    /**
     * Prefix of the disclosure label of a raw response line, completed with the header name.
     */
    case RAW_RESPONSE_LINE = 'Raw response line ';

    /**
     * Heading of the raw server variable table.
     */
    case RAW_SERVER_VARIABLES = 'Raw server variables';

    /**
     * Caption of the request body section.
     */
    case REQUEST_BODY = 'Request Body';

    /**
     * Heading of the server group carrying the request context variables.
     */
    case REQUEST_CONTEXT = 'Request context';

    /**
     * Accessible label of the tab strip.
     */
    case REQUEST_DATA = 'Request data';

    /**
     * Caption of the request header section.
     */
    case REQUEST_HEADERS_CAPTION = 'Request Headers';

    /**
     * Title of the inbound column in the header exchange.
     */
    case REQUEST_HEADERS_TITLE = 'Request headers';

    /**
     * Accessible label of the request overview.
     */
    case REQUEST_OVERVIEW = 'Request overview';

    /**
     * Caption of the response header section.
     */
    case RESPONSE_HEADERS_CAPTION = 'Response Headers';

    /**
     * Title of the outbound column in the header exchange.
     */
    case RESPONSE_HEADERS_TITLE = 'Response headers';

    /**
     * Overview field label of the resolved route, also used as a routing entry label.
     */
    case ROUTE = 'Route';

    /**
     * Caption of the route parameter section.
     */
    case ROUTE_PARAMETERS = 'Route parameters';

    /**
     * Caption of the routing section.
     */
    case ROUTING = 'Routing';

    /**
     * Heading of the routing trace when it inspected no rule.
     */
    case ROUTING_RESOLUTION = 'Routing resolution';

    /**
     * `sprintf()` template of the routing trace heading, naming how many rules were inspected.
     */
    case ROUTING_RESOLUTION_COUNT = 'Routing resolution (%d rules tested)';

    /**
     * Heading of the server group carrying the runtime and path variables.
     */
    case RUNTIME_PATHS = 'Runtime & paths';

    /**
     * Navigation label of the server tab, also the caption of its section.
     */
    case SERVER = 'Server';

    /**
     * Heading of the server detail disclosure.
     */
    case SERVER_DETAILS = 'Server details';

    /**
     * Navigation label of the session tab, also the caption of its section.
     */
    case SESSION = 'Session';

    /**
     * Overview field label of the capture time.
     */
    case TIME = 'Time';

    /**
     * Value shown when the capture resolved no action or duration.
     */
    case UNAVAILABLE = 'Unavailable';

    /**
     * Value shown when routing resolved no route.
     */
    case UNRESOLVED = 'Unresolved';

    /**
     * Hero title shown when the capture carried no URL.
     */
    case URL_UNAVAILABLE = 'URL unavailable';

    /**
     * Header of the variable value column.
     */
    case VALUE = 'Value';
}
