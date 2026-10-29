<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Laravel;

use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase;
use Waynestate\Advisor\Laravel\AdvisorServer;
use Waynestate\Advisor\Laravel\AdvisorServiceProvider;
use Waynestate\Advisor\Laravel\CorePrompt;
use Waynestate\Advisor\Laravel\CoreResource;
use Waynestate\Advisor\Laravel\CoreTool;
use Waynestate\Advisor\Server\Booted;

/** The Laravel adapter, beside Boost: same core, laravel/mcp's server class. */
final class AdvisorServerTest extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [McpServiceProvider::class, AdvisorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('advisor.env', ['ADVISOR_ENABLED' => '1', 'ADVISOR_PROJECT_ROOT' => dirname(__DIR__, 2)]);
    }

    public function testTheProviderBindsTheBootedCore(): void
    {
        $booted = $this->app->make(Booted::class);

        $this->assertInstanceOf(Booted::class, $booted);
        $this->assertCount(9, $booted->catalog->tools());
    }

    public function testTheServerOffersTheSameNineTools(): void
    {
        $this->assertSame(
            ['get_project_status', 'list_projects', 'list_stack_rules', 'owner_of', 'playbook_lookup', 'policy_lookup', 'read_project_docs', 'run_proctor_check', 'upgrade_path'],
            array_map(static fn(array $tool): string => $tool['name'], $this->listed()),
        );
    }

    public function testToolsCarryTheCoreSchemaAndHints(): void
    {
        foreach ($this->listed() as $tool) {
            $this->assertFalse($tool['inputSchema']['additionalProperties'], $tool['name']);
            $this->assertTrue($tool['annotations']['readOnlyHint'], $tool['name']);
            $this->assertFalse($tool['annotations']['destructiveHint'], $tool['name']);
        }
    }

    public function testACallRunsTheCoreTool(): void
    {
        AdvisorServer::tool($this->tool('list_projects'))->assertOk()->assertSee('wayne-ui');
        AdvisorServer::tool($this->tool('get_project_status'), ['project' => 'proctor'])->assertOk()->assertSee('PROCTOR');
        // No PLAYBOOK package in the test bench: the teaching answer, not an error.
        AdvisorServer::tool($this->tool('playbook_lookup'), ['key' => 'CAS_URL'])->assertOk()->assertSee('waynestate/playbook');
    }

    public function testBadArgumentsAreAnErrorTheModelCanFix(): void
    {
        AdvisorServer::tool($this->tool('get_project_status'), ['project' => 'nope'])->assertHasErrors();
    }

    public function testPromptsAndResourcesAreOffered(): void
    {
        AdvisorServer::prompt(new CorePrompt($this->booted()->catalog->prompts()['orient_me']), ['permutation' => 'laravel-inertia-vue'])->assertOk()->assertSee('laravel-inertia-vue');
        AdvisorServer::resource(new CoreResource($this->booted()->catalog->resources()['advisor://policy']))->assertOk()->assertSee('components');
    }

    private function booted(): Booted
    {
        return $this->app->make(Booted::class);
    }

    private function tool(string $name): CoreTool
    {
        return new CoreTool($this->booted()->catalog->tools()[$name], $this->booted()->invoker);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listed(): array
    {
        $response = AdvisorServer::tools();
        $items = (new \ReflectionProperty($response, 'items'))->getValue($response);

        return array_values($items);
    }
}
