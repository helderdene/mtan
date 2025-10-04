<?php

use App\Http\Middleware\SubdomainDetectionMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

describe('SubdomainDetectionMiddleware', function () {
    beforeEach(function () {
        $this->middleware = new SubdomainDetectionMiddleware;
    });

    test('detects admin subdomain', function () {
        $request = Request::create('https://admin.example.com/dashboard', 'GET');
        $request->headers->set('HOST', 'admin.example.com');

        $detected = false;
        $subdomain = null;

        $this->middleware->handle($request, function ($req) use (&$detected, &$subdomain) {
            $detected = $req->attributes->get('is_admin_subdomain');
            $subdomain = $req->attributes->get('subdomain');

            return response('OK');
        });

        expect($detected)->toBeTrue();
        expect($subdomain)->toBe('admin');
    });

    test('detects tenant subdomain', function () {
        $request = Request::create('https://acme.example.com/dashboard', 'GET');
        $request->headers->set('HOST', 'acme.example.com');

        $detected = false;
        $subdomain = null;

        $this->middleware->handle($request, function ($req) use (&$detected, &$subdomain) {
            $detected = $req->attributes->get('is_admin_subdomain');
            $subdomain = $req->attributes->get('subdomain');

            return response('OK');
        });

        expect($detected)->toBeFalse();
        expect($subdomain)->toBe('acme');
    });

    test('handles request without subdomain', function () {
        $request = Request::create('https://example.com/dashboard', 'GET');
        $request->headers->set('HOST', 'example.com');

        $subdomain = null;

        $this->middleware->handle($request, function ($req) use (&$subdomain) {
            $subdomain = $req->attributes->get('subdomain');

            return response('OK');
        });

        expect($subdomain)->toBeNull();
    });

    test('handles localhost with port', function () {
        $request = Request::create('http://admin.localhost:8000/dashboard', 'GET');
        $request->headers->set('HOST', 'admin.localhost:8000');

        $detected = false;
        $subdomain = null;

        $this->middleware->handle($request, function ($req) use (&$detected, &$subdomain) {
            $detected = $req->attributes->get('is_admin_subdomain');
            $subdomain = $req->attributes->get('subdomain');

            return response('OK');
        });

        expect($detected)->toBeTrue();
        expect($subdomain)->toBe('admin');
    });

    test('handles tenant subdomain with localhost', function () {
        $request = Request::create('http://tenant1.localhost:8000/dashboard', 'GET');
        $request->headers->set('HOST', 'tenant1.localhost:8000');

        $detected = false;
        $subdomain = null;

        $this->middleware->handle($request, function ($req) use (&$detected, &$subdomain) {
            $detected = $req->attributes->get('is_admin_subdomain');
            $subdomain = $req->attributes->get('subdomain');

            return response('OK');
        });

        expect($detected)->toBeFalse();
        expect($subdomain)->toBe('tenant1');
    });

    test('handles custom domain for tenant', function () {
        $request = Request::create('https://custom-domain.com/dashboard', 'GET');
        $request->headers->set('HOST', 'custom-domain.com');

        $subdomain = null;
        $isCustomDomain = false;

        $this->middleware->handle($request, function ($req) use (&$subdomain, &$isCustomDomain) {
            $subdomain = $req->attributes->get('subdomain');
            $isCustomDomain = $req->attributes->get('is_custom_domain');

            return response('OK');
        });

        expect($subdomain)->toBeNull();
        expect($isCustomDomain)->toBeTrue();
    });

    test('sets subdomain type attribute', function () {
        $request = Request::create('https://admin.example.com/dashboard', 'GET');
        $request->headers->set('HOST', 'admin.example.com');

        $subdomainType = null;

        $this->middleware->handle($request, function ($req) use (&$subdomainType) {
            $subdomainType = $req->attributes->get('subdomain_type');

            return response('OK');
        });

        expect($subdomainType)->toBe('admin');
    });

    test('sets tenant subdomain type', function () {
        $request = Request::create('https://acme.example.com/dashboard', 'GET');
        $request->headers->set('HOST', 'acme.example.com');

        $subdomainType = null;

        $this->middleware->handle($request, function ($req) use (&$subdomainType) {
            $subdomainType = $req->attributes->get('subdomain_type');

            return response('OK');
        });

        expect($subdomainType)->toBe('tenant');
    });

    test('handles www subdomain as root domain', function () {
        $request = Request::create('https://www.example.com/dashboard', 'GET');
        $request->headers->set('HOST', 'www.example.com');

        $subdomain = null;

        $this->middleware->handle($request, function ($req) use (&$subdomain) {
            $subdomain = $req->attributes->get('subdomain');

            return response('OK');
        });

        expect($subdomain)->toBeNull();
    });

    test('extracts subdomain from multi-level domain', function () {
        $request = Request::create('https://tenant1.app.example.com/dashboard', 'GET');
        $request->headers->set('HOST', 'tenant1.app.example.com');

        $subdomain = null;

        $this->middleware->handle($request, function ($req) use (&$subdomain) {
            $subdomain = $req->attributes->get('subdomain');

            return response('OK');
        });

        expect($subdomain)->toBe('tenant1');
    });
});
