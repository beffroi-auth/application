<?php

declare(strict_types=1);

namespace App\Saml;

final class IdentityProviderRoutes
{
    public const string METADATA = 'beffroi_protocol_saml_metadata';

    public const string METADATA_PATH = '/saml/metadata';

    public const string SINGLE_SIGN_ON = 'beffroi_interaction_saml_single_sign_on';

    public const string SINGLE_SIGN_ON_PATH = '/saml/sso';

    public const string SINGLE_SIGN_ON_RESUME = 'beffroi_interaction_saml_single_sign_on_resume';

    public const string SINGLE_SIGN_ON_RESUME_PATH = '/saml/sso/resume';

    public const string REFERENCE_PARAMETER = 'request';

    public const string SINGLE_LOGOUT = 'beffroi_interaction_saml_single_logout';

    public const string SINGLE_LOGOUT_PATH = '/saml/slo';

    public const string SINGLE_LOGOUT_CONTINUE = 'beffroi_interaction_saml_single_logout_continue';

    public const string SINGLE_LOGOUT_CONTINUE_PATH = '/saml/slo/continue';

    public const string CHAIN_PARAMETER = 'chain';

    private function __construct()
    {
    }
}
