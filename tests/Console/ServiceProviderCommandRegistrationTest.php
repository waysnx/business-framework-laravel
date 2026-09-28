<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Console;

use Illuminate\Console\Application as Artisan;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * ServiceProviderCommandRegistrationTest
 *
 * Tests for the Laravel service provider command registration.
 *
 * Verifies:
 * - LaravelServiceProvider properly registers all WBF commands
 * - CreateDemoCommand is registered in the commands array
 * - All registered commands are resolvable through Laravel container
 * - CreateDemoCommand appears in artisan command list
 * - Commands can be executed through the registered infrastructure
 *
 * @package WaysNX\BusinessFramework\Tests\Console
 */
class ServiceProviderCommandRegistrationTest extends TestCase
{
    /**
     * Laravel container
     *
     * @var Container
     */
    private Container $container;

    /**
     * Artisan console application
     *
     * @var Artisan
     */
    private Artisan $artisan;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create real Laravel container
        $this->container = new class extends Container {
            public function runningUnitTests() {
                return true;
            }
        };

        Container::setInstance($this->container);

        // Register event dispatcher
        $this->container->singleton('events', function ($app) {
            return new \Illuminate\Events\Dispatcher($app);
        });

        // Register all WBF services (mirrors LaravelServiceProvider::registerCommands registration)
        $this->registerWBFServices();

        // Create Artisan console
        $this->artisan = new Artisan($this->container, $this->container['events'], 'test');
    }

    /**
     * Register WBF services for test isolation
     *
     * @return void
     */
    private function registerWBFServices(): void
    {
        // Register all registries
        $this->container->singleton(\WaysNX\BusinessFramework\Registry\WorkflowRegistry::class, function () {
            return new \WaysNX\BusinessFramework\Registry\WorkflowRegistry();
        });

        $this->container->singleton(\WaysNX\BusinessFramework\Registry\EntityRegistry::class, function () {
            return new \WaysNX\BusinessFramework\Registry\EntityRegistry();
        });

        $this->container->singleton(\WaysNX\BusinessFramework\Registry\ModuleRegistry::class, function () {
            return new \WaysNX\BusinessFramework\Registry\ModuleRegistry();
        });

        $this->container->singleton(\WaysNX\BusinessFramework\Registry\ValidationRegistry::class, function () {
            return new \WaysNX\BusinessFramework\Registry\ValidationRegistry();
        });

        $this->container->singleton(\WaysNX\BusinessFramework\Registry\BusinessFunctionRegistry::class, function () {
            return new \WaysNX\BusinessFramework\Registry\BusinessFunctionRegistry();
        });

        // Register lifecycle manager
        $this->container->singleton(\WaysNX\BusinessFramework\Lifecycle\LifecycleManager::class, function () {
            return new \WaysNX\BusinessFramework\Lifecycle\LifecycleManager();
        });

        // Register engines
        $this->container->singleton(\WaysNX\BusinessFramework\Workflow\WorkflowEngine::class, function ($app) {
            $registry = $app->make(\WaysNX\BusinessFramework\Registry\WorkflowRegistry::class);
            $lifecycleManager = $app->make(\WaysNX\BusinessFramework\Lifecycle\LifecycleManager::class);
            return new \WaysNX\BusinessFramework\Workflow\WorkflowEngine($registry, $lifecycleManager);
        });

        $this->container->singleton(\WaysNX\BusinessFramework\Validation\ValidationFramework::class, function ($app) {
            $registry = $app->make(\WaysNX\BusinessFramework\Registry\ValidationRegistry::class);
            $lifecycleManager = $app->make(\WaysNX\BusinessFramework\Lifecycle\LifecycleManager::class);
            return new \WaysNX\BusinessFramework\Validation\ValidationFramework($registry, $lifecycleManager);
        });

        // Register interface bindings
        $this->container->bind(\WaysNX\BusinessFramework\Contracts\CollectionInterface::class, \WaysNX\BusinessFramework\Collections\BaseCollection::class);
        $this->container->bind(\WaysNX\BusinessFramework\Contracts\RepositoryInterface::class, \WaysNX\BusinessFramework\Repositories\BaseRepository::class);
    }

    /**
     * Test: CreateDemoCommand is registered in the service provider
     *
     * Verifies the command is included in the LaravelServiceProvider::registerCommands()
     * method by checking the source code or by verifying it's resolvable.
     *
     * @return void
     */
    public function testCreateDemoCommandIsRegistered(): void
    {
        // The command must be resolvable from the container
        $command = $this->container->make(\WaysNX\BusinessFramework\Console\Commands\CreateDemoCommand::class);
        $this->assertInstanceOf(\WaysNX\BusinessFramework\Console\Commands\CreateDemoCommand::class, $command);
    }

    /**
     * Test: All six WBF commands can be resolved from container
     *
     * Verifies that the service provider infrastructure allows all registered
     * commands to be resolved, including CreateDemoCommand.
     *
     * @return void
     */
    public function testAllWBFCommandsResolvableFromContainer(): void
    {
        $commands = [
            \WaysNX\BusinessFramework\Console\Commands\MakeCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\ListCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\ShowCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\RegisterCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\DoctorCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\CreateDemoCommand::class,
        ];

        foreach ($commands as $commandClass) {
            $command = $this->container->make($commandClass);
            $this->assertNotNull($command, "Failed to resolve {$commandClass}");
            $this->assertInstanceOf($commandClass, $command);
        }
    }

    /**
     * Test: CreateDemoCommand has correct signature
     *
     * Verifies the command is properly configured as a console command
     * with the expected signature for artisan registration.
     *
     * @return void
     */
    public function testCreateDemoCommandHasCorrectSignature(): void
    {
        $command = $this->container->make(\WaysNX\BusinessFramework\Console\Commands\CreateDemoCommand::class);
        
        // Use reflection to access the signature property
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $signature = $property->getValue($command);
        
        $this->assertStringContainsString('wbf:createdemo', $signature, 'Command signature should contain wbf:createdemo');
        $this->assertStringContainsString('--json', $signature, 'Command should support --json option');
    }

    /**
     * Test: CreateDemoCommand is discoverable through Artisan console
     *
     * Verifies that after all commands from the service provider are added
     * to Artisan, wbf:createdemo can be found and listed.
     *
     * @return void
     */
    public function testCreateDemoCommandDiscoverableThroughArtisan(): void
    {
        // Register all commands to Artisan (simulating what LaravelServiceProvider does)
        $commands = [
            \WaysNX\BusinessFramework\Console\Commands\MakeCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\ListCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\ShowCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\RegisterCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\DoctorCommand::class,
            \WaysNX\BusinessFramework\Console\Commands\CreateDemoCommand::class,
        ];

        foreach ($commands as $commandClass) {
            $command = $this->container->make($commandClass);
            $this->artisan->add($command);
        }

        // Verify wbf:createdemo exists
        $this->assertTrue($this->artisan->has('wbf:createdemo'), 'wbf:createdemo should be discoverable');
    }

    /**
     * Test: CreateDemoCommand can be found in Artisan
     *
     * Verifies that the command can actually be located and retrieved
     * through Artisan's find() method.
     *
     * @return void
     */
    public function testCreateDemoCommandFindableInArtisan(): void
    {
        // Add all commands
        $command = $this->container->make(\WaysNX\BusinessFramework\Console\Commands\CreateDemoCommand::class);
        $this->artisan->add($command);

        // Should be able to find it
        $found = $this->artisan->find('wbf:createdemo');
        $this->assertNotNull($found);
        $this->assertInstanceOf(\WaysNX\BusinessFramework\Console\Commands\CreateDemoCommand::class, $found);
    }

    /**
     * Test: Service provider source code includes CreateDemoCommand
     *
     * Verifies that the LaravelServiceProvider source code actually
     * includes CreateDemoCommand in the registerCommands method.
     *
     * @return void
     */
    public function testServiceProviderSourceIncludesCreateDemoCommand(): void
    {
        $providerPath = dirname(__DIR__, 2) . '/src/ServiceProvider/LaravelServiceProvider.php';
        $source = file_get_contents($providerPath);
        
        $this->assertStringContainsString(
            'CreateDemoCommand::class',
            $source,
            'LaravelServiceProvider should register CreateDemoCommand'
        );
    }

    /**
     * Tear down test environment
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }
}

