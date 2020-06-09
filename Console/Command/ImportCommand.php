<?php

namespace Ingenico\Import\Console\Command;

use Ingenico\Import\Model\Import;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportCommand extends Command
{
    const FILE = 'file';
    const PASSWORD = 'password';

    /**
     * @var Import
     */
    protected $import;

    /**
     * @var \Ingenico\Import\Helper\Encryption
     */
    protected $encryption;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('ingenico:import');
        $this->setDescription('Imports data into database.');

        $this->addOption(
            self::FILE,
            null,
            InputOption::VALUE_REQUIRED,
            'File'
        );

        $this->addOption(
            self::PASSWORD,
            null,
            InputOption::VALUE_REQUIRED,
            'Password'
        );

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getOption(self::FILE);
        $password = $input->getOption(self::PASSWORD);

        // phpcs:disable
        if (!file_exists($file)) {
        // phpcs:enable
            $output->writeln('<error>Provided file `' . $file . '` is not exists</error>');
            return Cli::RETURN_FAILURE;
        }

        if (empty($password)) {
            $output->writeln('<error>Provided password `' . $password . '` is empty</error>');
            return Cli::RETURN_FAILURE;
        }

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $this->import = $om->get(\Ingenico\Import\Model\Import::class);
        $this->encryption = $om->get(\Ingenico\Import\Helper\Encryption::class);

        $output->writeln('<comment>Loading the file...</comment>');
        $content = file_get_contents($file); //@codingStandardsIgnoreLine

        $output->writeln('<comment>Decrypting...</comment>');
        $content = $this->encryption->decrypt($content, $password);
        if (!$content) {
            $output->writeln('<error>Invalid password.</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<comment>Initialize import</comment>');
        $data = json_decode($content, true);
        $storesConfig = $data['stores'];
        $config = $data['config'];
        $aliases = $data['aliases'];

        $output->writeln('<comment>Stores configuration: ' .  var_export($storesConfig, true) . '</comment>');

        // Import configuration
        $output->writeln('<comment>Importing configuration...</comment>');
        $this->import->importConfig($config, $storesConfig);

        // Import Aliases
        //$this->import->importAliases($aliases);
        $output->writeln('<comment>Importing aliases...</comment>');
        $progressBar = new ProgressBar($output, count($aliases));
        $progressBar->start();
        foreach ($aliases as $alias) {
            try {
                $this->import->importAlias($alias);
            } catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                continue;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('<info>Data successfully imported!</info>');

        return Cli::RETURN_SUCCESS;
    }
}
