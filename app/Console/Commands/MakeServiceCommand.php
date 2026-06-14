<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeServiceCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class and optionally its contract';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Service';

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws FileNotFoundException
     */
    public function handle()
    {
        if (parent::handle() === false) {
            return false;
        }

        if ($this->input->hasParameterOption('--withContract') && $this->option('withContract') === null) {
            $this->createContract();
        }
    }

    /**
     * Create a contract for the service.
     *
     * @return void
     */
    protected function createContract()
    {
        $contractName = class_basename($this->getNameInput()) . 'Interface';

        $path = $this->getContractPath($contractName);

        if ($this->alreadyExists($path)) {
            $this->error($this->type . ' contract already exists!');

            return;
        }

        $this->makeDirectory($path);

        $stub = $this->files->get($this->getContractStub());

        $this->files->put($path, $this->buildContractClass($contractName, $stub));

        $this->info($this->type . ' contract created successfully.');
    }

    /**
     * Get the path for the contract.
     *
     * @return string
     */
    protected function getContractPath($name)
    {
        return $this->laravel['path'] . '/Services/Contracts/' . $name . '.php';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return base_path('stubs/service.stub');
    }

    /**
     * Get the contract stub file for the generator.
     *
     * @return string
     */
    protected function getContractStub()
    {
        return base_path('stubs/service.contract.stub');
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Services';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @return string
     *
     * @throws FileNotFoundException
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        if (! $this->input->hasParameterOption('--withContract')) {
            // No contract, so remove placeholders
            return str_replace(
                ['use {{ contractNamespace }}\\{{ contract }};', ' implements {{ contract }}'],
                ['', ''],
                $stub
            );
        }

        $contractOptionValue = $this->option('withContract');

        if ($contractOptionValue === null) { // Default contract
            $contract = class_basename($name) . 'Interface';
        } else { // Custom contract
            $contract = Str::studly($contractOptionValue);
        }

        return str_replace(
            ['{{ contractNamespace }}', '{{ contract }}'],
            ['App\\Services\\Contracts', $contract],
            $stub
        );
    }

    /**
     * Build the contract class with the given name.
     *
     * @param  string  $name
     * @param  string  $stub
     *
     * @return string
     */
    protected function buildContractClass($name, $stub)
    {
        return str_replace(
            ['{{ namespace }}', '{{ class }}'],
            ['App\\Services\\Contracts', $name],
            $stub
        );
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        $name = trim($this->argument('name'));
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        $parts = explode('/', $name);
        $lastPart = array_pop($parts);
        $lastPart = Str::studly($lastPart);

        if (! Str::endsWith($lastPart, 'Service')) {
            $lastPart .= 'Service';
        }

        $parts[] = $lastPart;

        return implode('\\', $parts);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the service.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['withContract', null, InputOption::VALUE_OPTIONAL, 'Create a contract interface for the service. Optionally specify the contract name.'],
        ];
    }
}
