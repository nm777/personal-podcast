<?php

use App\Exceptions\Handler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

describe('Exception handler', function () {
    it('does not intercept web routes — returns Inertia redirect for auth', function () {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    });

    it('returns JSON for unauthenticated JSON requests', function () {
        $response = $this->getJson('/feeds');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns JSON for validation errors on JSON requests', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/feeds', [
            'name' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
    });

    it('renderable callback only handles JSON requests', function () {
        $handler = app(Handler::class);

        $jsonRequest = Request::create('/test', 'GET');
        $jsonRequest->headers->set('Accept', 'application/json');
        $response = $handler->render($jsonRequest, new NotFoundHttpException('Test'));
        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKey('error');
        expect($data['code'])->toBe('ENDPOINT_NOT_FOUND');

        $webRequest = Request::create('/test', 'GET');
        $webResponse = $handler->render($webRequest, new NotFoundHttpException('Test'));
        expect($webResponse->getContent())->not->toContain('"error"');
    });
});
