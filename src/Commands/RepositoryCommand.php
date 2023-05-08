<?php

namespace Raditor\DesignPattern\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;

class RepositoryCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name : Create a repository class} {--m= : model name} {--i : Create a repository interface}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository class';

    /**
     * The console command type.
     *
     * @var string
     */
    protected $type = 'Repository file';

    protected function getNameInput()
    {
        return str_replace('.', '/', trim($this->argument('name')));
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        // First we need to ensure that the given name is not a reserved word within the PHP
        // language and that the class name will actually be valid. If it is not valid we
        // can error now and prevent from polluting the filesystem using invalid files.
        if ($this->isReservedName($this->getNameInput())) {
            $this->components->error('The name "' . $this->getNameInput() . '" is reserved by PHP.');

            return false;
        }

        $name = $this->qualifyClass($this->getNameInput());

        $path = $this->getPath($name);

        // Next, We will check to see if the class already exists. If it does, we don't want
        // to create the class and overwrite the user's code. So, we will bail out so the
        // code is untouched. Otherwise, we will continue generating this class' files.
        if ((!$this->hasOption('force') ||
                !$this->option('force')) &&
            $this->alreadyExists($this->getNameInput())
        ) {
            $this->components->error($this->type . ' already exists.');

            return false;
        }

        //auto add model
        $model = $this->option('m');
        if ($model) {
            Artisan::call('make:model', ['name' => $model]);
        }

        // Next, we will generate the path to the location where this class' file should get
        // written. Then, we will build the class and make the proper replacements on the
        // stub files so that it gets the correctly formatted namespace and class name.
        $this->makeDirectory($path);

        //
        $isInterface = $this->option('i');

        $this->files->put($path, $this->sortImports($this->buildRepositoryClass($name, $isInterface)));

        $info = $this->type;

        if ($isInterface) {
            $interfaceName = $this->getNameInput() . 'Interface.php';
            $interfacePath = str_replace($this->getNameInput() . '.php', 'Interfaces/', $path);

            $this->makeDirectory($interfacePath . $interfaceName);

            $this->files->put(
                $interfacePath . $interfaceName,
                $this->sortImports(
                    $this->buildRepositoryInterface($this->getNameInput())
                )
            );

            $info .= ' and interface';

            $interfacePath = $this->laravel['path'] . $interfacePath;

            $path = implode(',', array_merge([$path], [$interfacePath]));
        }

        if (in_array(CreatesMatchingTest::class, class_uses_recursive($this))) {
            if ($this->handleTestCreation($path)) {
                $info .= ' and test';
            }
        }

        $this->components->info(sprintf('%s [%s] created successfully.', $info, $path));
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */

    protected function buildRepositoryClass($name, $isInterface)
    {
        $model = $this->option('m');

        $stub = $isInterface ? $this->buildRepositoryWithInterfaceClass($name) : $this->buildRepositoryWithModelOrNotClass($name);
        if ($model) {
            return $this->replaceModel($stub, $model);
        }
        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    protected function buildRepositoryWithModelOrNotClass($name)
    {
        $model = $this->option('m');
        if ($model) {
            $stub = $this->files->get($this->getRepositoryInterfaceModelStub());
        } else {
            $stub = $this->files->get($this->getRepositoryInterfaceStub());
        }

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    protected function buildRepositoryWithInterfaceClass($name)
    {
        $model = $this->option('m');
        if ($model) {
            $stub = $this->files->get($this->getStubRepositoryModel());
        } else {
            $stub = $this->files->get($this->getStubRepository());
        }

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    protected function buildRepositoryInterface(string $name): string
    {
        $stub = $this->files->get($this->getInterfaceStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Replace the model for the given stub.
     *
     * @param  string  $stub
     * @param  string  $model
     * @return string
     */
    protected function replaceModel($stub, $model)
    {
        $modelClass = $this->parseModel($model);

        $replace = [
            '{{ namespacedModel }}' => $modelClass,
            '{{ m }}' => class_basename($modelClass),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
        ];

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $stub
        );
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return  __DIR__ . '/Stubs/repository/repository.stub';
    }

    protected function getStubRepository()
    {
        return  $this->getStub();
    }

    protected function getStubRepositoryModel()
    {
        return  __DIR__ . '/Stubs/repository/repository-model.stub';
    }

    protected function getRepositoryInterfaceStub()
    {
        return  __DIR__ . '/Stubs/repository/repository.interface.stub';
    }

    protected function getRepositoryInterfaceModelStub()
    {
        return  __DIR__ . '/Stubs/repository/repository-model.interface.stub';
    }

    protected function getInterfaceStub()
    {
        return  __DIR__ . '/Stubs/repository/interface.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Repositories';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Model'],
        ];
    }
}
