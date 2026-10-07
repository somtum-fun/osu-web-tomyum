{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $appUrl = $GLOBALS['cfg']['app']['url'];
    $searchUrl = route('search');
@endphp
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/" xmlns:moz="http://www.mozilla.org/2006/browser/search/">
    <ShortName>{{ App\Libraries\TomYumBranding::name() }}</ShortName>
    <Description>{{ App\Libraries\TomYumBranding::name() }} search</Description>
    <Url method="get" template="{{ $searchUrl }}?query={searchTerms}" type="text/html"/>
    <Image height="16" width="16" type="{{ App\Libraries\TomYumBranding::faviconType() }}">{{ $appUrl }}{{ App\Libraries\TomYumBranding::favicon('favicon-16x16.png') }}</Image>
    <Image height="32" width="32" type="{{ App\Libraries\TomYumBranding::faviconType() }}">{{ $appUrl }}{{ App\Libraries\TomYumBranding::favicon('favicon-32x32.png') }}</Image>
    <moz:SearchForm>{{ $searchUrl }}</moz:SearchForm>
    <Url type="application/opensearchdescription+xml" rel="self" template="{{ $appUrl }}/opensearch.xml" />
</OpenSearchDescription>
