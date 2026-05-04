<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\JwtService;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private const SECRET = 'phpunit-secret-32chars-XXXXXXXXXX';

    public function test_issue_usa_ttl_del_constructor_si_no_se_pasa_override(): void
    {
        $svc = new JwtService(self::SECRET, ttlHours: 5);
        $token = $svc->issue(1, 'super_admin', null);

        $payload = $svc->decode($token);
        // exp = iat + 5*3600 (con tolerancia de 2 segundos por timing)
        $diff = $payload['exp'] - $payload['iat'];
        $this->assertEqualsWithDelta(5 * 3600, $diff, 2);
    }

    public function test_issue_usa_ttl_override_si_se_pasa(): void
    {
        $svc = new JwtService(self::SECRET, ttlHours: 2);
        $token = $svc->issue(1, 'super_admin', null, ttlHoursOverride: 720);

        $payload = $svc->decode($token);
        $diff = $payload['exp'] - $payload['iat'];
        $this->assertEqualsWithDelta(720 * 3600, $diff, 2);
    }

    public function test_issue_clamp_ttl_minimo_a_1_si_es_0_o_negativo(): void
    {
        $svc = new JwtService(self::SECRET, ttlHours: 5);
        $token = $svc->issue(1, 'super_admin', null, ttlHoursOverride: 0);

        $payload = $svc->decode($token);
        $diff = $payload['exp'] - $payload['iat'];
        $this->assertEqualsWithDelta(3600, $diff, 2);
    }

    public function test_default_ttl_seconds_devuelve_el_ttl_del_constructor(): void
    {
        $svc = new JwtService(self::SECRET, ttlHours: 720);
        $this->assertSame(720 * 3600, $svc->defaultTtlSeconds());
    }
}
