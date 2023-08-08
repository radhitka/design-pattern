<?php

namespace Raditor\DesignPattern\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\GeneratorCommand;

class ServiceCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name : Create a service class} {--i : Create a service interface}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a service and contract';

    /**
     * Execute the console command.
     *
     * @return int
     */

    /**
     * The console command type.
     *
     * @var string
     */
    protected $type = 'Service file';

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
            $this->error('The name "' . $this->getNameInput() . '" is reserved by PHP.');

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
            $this->error($this->type . ' already exists!');

            return false;
        }

        // Next, we will generate the path to the location where this class' file should get
        // written. Then, we will build the class and make the proper replacements on the
        // stub files so that it gets the correctly formatted namespace and class name.
        $this->makeDirectory($path);

        //
        $isInterface = $this->option('i');

        $this->files->put($path, $this->sortImports($this->buildServiceClass($name, $isInterface)));

        $info = $this->type;

        if ($isInterface) {
            $interfaceName = $this->getNameInput() . 'Interface.php';
            $interfacePath = str_replace($this->getNameInput() . '.php', 'Interfaces/', $path);

            $this->makeDirectory($interfacePath . $interfaceName);

            $this->files->put(
                $interfacePath . $interfaceName,
                $this->sortImports(
                    $this->buildServiceInterface($this->getNameInput())
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

        $this->info(sprintf('%s [%s] created successfully.', $info, $path));
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildServiceClass($name, $isInterface)
    {
        return $isInterface ? $this->buildServiceWithInterfaceClass($name) : parent::buildClass($name);
    }

    protected function buildServiceWithInterfaceClass($name)
    {
        $stub = $this->files->get($this->getRepositoryInterfaceStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    protected function buildServiceInterface(string $name): string
    {
        $stub = $this->files->get($this->getInterfaceStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return  __DIR__ . '/Stubs/service/service.stub';
    }

    protected function getRepositoryInterfaceStub()
    {
        return  __DIR__ . '/Stubs/service/service.interface.stub';
    }

    protected function getInterfaceStub()
    {
        return  __DIR__ . '/Stubs/service/interface.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Services';
    }
}
