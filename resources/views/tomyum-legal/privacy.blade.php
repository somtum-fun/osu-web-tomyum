{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: this server's Privacy Policy, as on example.com. --}}
@extends('tomyum-legal.layout')

@section('legal-updated', 'Effective Date: 7 June 2026')

@section('legal-body')
<h2>1. Introduction</h2>
<p>
    {{ App\Libraries\TomYumBranding::name() }} ("we," "us," or "our") is a private osu! server operated by an individual person and managed by
    the {{ App\Libraries\TomYumBranding::name() }} team. Your privacy is important to us, and this Privacy Policy explains how we collect, use, and
    protect your personal data.
</p>
<p>
    By registering and using {{ App\Libraries\TomYumBranding::name() }}, you agree to the collection and use of information in accordance with
    this policy.
</p>

<h2>2. Data Collection</h2>
<p>We collect and store the following personal data when you register and use our services:</p>
<ul>
    <li>Username</li>
    <li>Email Address</li>
    <li>Password (securely hashed using bcrypt + salt)</li>
    <li>IP Address and Country</li>
    <li>Gameplay Data (scores, rankings, achievements, and related stats)</li>
</ul>

<h3>2.1 On Updating Your Profile</h3>
<p>
    When building your user profile (which is publicly visible to all other users), you can optionally provide:
</p>
<ul>
    <li>Your current location</li>
    <li>Your interests</li>
    <li>Your occupation</li>
    <li>Your social media presence (Twitter, Discord, Skype, website)</li>
    <li>Your avatar and profile cover images</li>
    <li>Your signature</li>
</ul>
<p>
    All of the above fields are publicly visible but can be removed immediately and permanently at any time via
    the settings page.
</p>

<h3>2.2 On Uploading User-Submitted Content</h3>
<p>
    When posting on the forums, participating in chat, or uploading content to our service such as a beatmap,
    you are expressly making all submitted content public. In most cases, it can be edited or deleted after
    submission at your discretion. However, in certain cases, this functionality may be restricted to maintain
    the relevance and integrity of the service.
</p>
<p>
    For example, if you upload a beatmap and it is "ranked," it becomes the basis for the wider user base to
    achieve scores. At that point, the option to delete the submission will be revoked.
</p>

<h3>2.3 On Logging In to the Game Client</h3>
<p>
    When connecting to our service from the osu! game client, a client-specific string is submitted to help us
    identify your current play environment. This string is generated from a combination of hardware and
    software identifiers, and hashed so that it contains no personally identifiable information. However, it
    can be used to track access across logins.
</p>
<p>
    The primary purpose of this is to maintain a fair ranking system and to enforce account security in case of
    unauthorized access. This data is considered private, stored only as long as necessary, and is
    non-transferable.
</p>

<h3>2.4 On Playing the Game and Submitting a Score</h3>
<p>
    When completing a game session (whether passing or failing a beatmap), details of your performance are
    automatically submitted to our servers. This submission includes replay data and scoring information, which
    may be displayed publicly on the Global Leaderboards and your User Profile. These records cannot be deleted
    or modified.
</p>

<h3>2.5 Anti-Cheat System</h3>
<p>
    Our anti-cheat system operates entirely on our private servers. We do not inject code
    into your osu! client, nor do we spy on or violate your privacy. We strive to avoid tracking any personally
    identifiable data while processing replays for anti-cheat purposes.
</p>

<h2>3. Use of Data</h2>
<p>Your data is used strictly for the following purposes:</p>
<ul>
    <li><strong>Gameplay Analytics &amp; Leaderboards</strong> – To provide an engaging experience with accurate
        score tracking.</li>
    <li><strong>Security &amp; Moderation</strong> – To prevent cheating, enforce rules, and manage
        bans/appeals.</li>
    <li><strong>Account Management</strong> – To allow users to log in, play, and interact with the
        community.</li>
</ul>
<p>
    We do not share, sell, or distribute your data to third parties. Only the {{ App\Libraries\TomYumBranding::name() }} team has access to this
    data.
</p>

<h2>4. Cookies &amp; Tracking</h2>
<p>
    We do not use cookies or tracking technologies to monitor user activity beyond what is necessary for site
    functionality.
</p>
<p>All website services are handled by Cloudflare to ensure security, performance, and reliability.</p>

<h2>5. Payment Information</h2>
<p>
    If you make donations or purchases, we only collect your legal name. We do not have access to your
    debit/credit card details, as payments are processed securely by third-party payment processors.
</p>
<p>For more details, please refer to the respective payment processor's privacy policy:</p>
{{-- TomYum: the payment processors (Admin panel > Branding). --}}
{!! markdown_plain(App\Libraries\TomYumBranding::text('privacy_payments')) !!}

<h2>6. Data Storage and Backup</h2>
<p>Your data and our server infrastructure are backed up and hosted by:</p>
{{-- TomYum: where the server is hosted (Admin panel > Branding). --}}
{!! markdown_plain(App\Libraries\TomYumBranding::text('privacy_hosting')) !!}
<p>This provider is used to maintain high availability, redundancy, and performance.</p>

<h2>7. Data Retention</h2>
<p>
    We retain user data until a deletion request is made. Users can request to delete their data by contacting
    <a href="mailto:{{ App\Libraries\TomYumTickets::address('privacy') }}">{{ App\Libraries\TomYumTickets::address('privacy') }}</a>.
</p>
{{-- TomYum: the sign-up checks (TomYumRegistrationGuard). --}}
<p>
    When you make an account, we check the network you sign up from (to stop VPN, proxy and multiple
    sign-ups) and may ask proxycheck.io about your IP address. We keep a log of sign-ups, with the IP address,
    network and country, for 90 days, then delete it.
</p>

<h2>8. Data Protection &amp; Security</h2>
<p>We follow privacy regulations based on the user's region:</p>
{{-- TomYum: the data protection laws followed (Admin panel > Branding). --}}
{!! markdown_plain(App\Libraries\TomYumBranding::text('privacy_laws')) !!}
<p>Security measures include:</p>
<ul>
    <li>Encryption of stored passwords (bcrypt + salt).</li>
    <li>Access Control to ensure only authorized personnel can manage data.</li>
    <li>Regular Security Audits to detect vulnerabilities.</li>
</ul>

<h2>9. User Rights</h2>
<ul>
    <li><strong>Access &amp; Deletion:</strong> Users can request access to their data or request deletion by
        contacting <a href="mailto:{{ App\Libraries\TomYumTickets::address('privacy') }}">{{ App\Libraries\TomYumTickets::address('privacy') }}</a>.</li>
    <li><strong>Account Appeals:</strong> Users who have been banned may appeal by emailing
        <a href="mailto:{{ App\Libraries\TomYumTickets::address('accounts') }}">{{ App\Libraries\TomYumTickets::address('accounts') }}</a>.</li>
</ul>

<h2>10. Changes to This Privacy Policy</h2>
<p>We may update this Privacy Policy from time to time. Users will be notified of changes via email.</p>

<h2>11. Contact Information</h2>
<p>For any privacy concerns, contact us at:</p>
<p>Email: <a href="mailto:{{ App\Libraries\TomYumTickets::address('privacy') }}">{{ App\Libraries\TomYumTickets::address('privacy') }}</a></p>
<p>By using {{ App\Libraries\TomYumBranding::name() }}, you acknowledge that you have read and understood this Privacy Policy.</p>
@endsection
