<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Command;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Flex\Recipe;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Ryan Weaver <weaverryan@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    /** @var ConsoleStyle */
    protected $io;
    /** @var InputInterface */
    protected $input;
    private $generator;
    private $checkDependencies = true;
    private $nonInteractiveArguments = [];

    public function __construct(Generator $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    /**
     * Returns the parameters used to parse the file templates, to generate the
     * file names, etc.
     */
    abstract protected function getParameters() : array;

    /**
     * Returns the list of files to generate and the templates used to do that.
     */
    abstract protected function getFiles(array $params) : array;

    /**
     * Override to add a final "next steps" message.
     *
     * @param array $params
     * @param ConsoleStyle $io
     */
    abstract protected function writeNextStepsMessage(array $params, ConsoleStyle $io);

    abstract protected function configureDependencies(DependencyBuilder $dependencies);

    /**
     * Call in configure() to disable the automatic interactive prompt for an arg.
     *
     * @param string $argumentName
     */
    protected function setArgumentAsNonInteractive($argumentName)
    {
        $this->nonInteractiveArguments[] = $argumentName;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new ConsoleStyle($input, $output);
        $this->input = $input;

        if ($this->checkDependencies) {
            if (!class_exists(Recipe::class)) {
                throw new RuntimeCommandException(sprintf('The generator commands require your app to use Symfony Flex & a Flex directory structure. See https://symfony.com/doc/current/setup/flex.html'));
            }

            $dependencies = new DependencyBuilder();
            $this->configureDependencies($dependencies);
            if ($missingPackages = $dependencies->getMissingDependencies()) {
                throw new RuntimeCommandException(sprintf("Missing package%s: to use the %s command, run: \n\ncomposer require %s", count($missingPackages) === 1 ? '' : 's', $this->getName(), implode(' ', $missingPackages)));
            }
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->getDefinition()->getArguments() as $argument) {
            if ($input->getArgument($argument->getName())) {
                continue;
            }

            if (in_array($argument->getName(), $this->nonInteractiveArguments)) {
                continue;
            }

            $value = $this->io->ask($argument->getDescription(), $argument->getDefault());
            $input->setArgument($argument->getName(), $value);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->generator->setIO($this->io);
        $params = $this->getParameters();
        $this->generator->generate($params, $this->getFiles($params));

        $this->io->newLine();
        $this->io->writeln(' <bg=green;fg=white>          </>');
        $this->io->writeln(' <bg=green;fg=white> Success! </>');
        $this->io->writeln(' <bg=green;fg=white>          </>');
        $this->io->newLine();

        $this->writeNextStepsMessage($params, $this->io);
    }

    /**
     * @internal Used for testing commands
     */
    public function setCheckDependencies(bool $checkDeps)
    {
        $this->checkDependencies = $checkDeps;
    }

    /**
     * @internal Used for testing commands
     */
    public function setGenerator(Generator $generator)
    {
        $this->generator = $generator;
    }
}
