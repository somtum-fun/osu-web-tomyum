{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
<script data-turbo-eval="always">
    var csrf = "{{ csrf_token() }}";
    var canonicalUrl = "{{ $canonicalUrl ?? '' }}";
    {{-- TomYum: the domain of this server's ticket addresses (dmca@, support@, …). --}}
    var ticketsDomain = {!! json_encode(App\Libraries\TomYumTickets::domain()) !!};
    {{-- TomYum: this server's name, ":brand" in the texts (TomYumBranding). --}}
    var brandName = {!! json_encode(App\Libraries\TomYumBranding::name()) !!};
</script>

@include ('layout._current_user')
