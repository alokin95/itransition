<?php

declare(strict_types=1);

namespace App\Console;

use App\Application\Import\ProductImporter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

#[AsCommand(name: 'app:import-products-from-file', description: 'Import products from a CSV file.')]
class ImportProductsFromFileCommand extends Command
{
    public function __construct(private readonly ProductImporter $importer)
    {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Path to the CSV file.')]
        string $filepath = 'storage/stock.csv',
        #[Option(description: 'Run import without persisting to the database.')]
        bool $test = false,
    ): int {
        $io = new SymfonyStyle($input, $output);

        if (!file_exists($filepath)) {
            $io->error(sprintf('File "%s" does not exist.', $filepath));

            return Command::INVALID;
        }

        if ($test) {
            $io->note('Running in test mode; nothing will be saved to the database.');
        }

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat(' %current% rows [%bar%] %elapsed:6s%');
        $progressBar->start();

        $result = $this->importer->import($filepath, $test, static function () use ($progressBar): void {
            $progressBar->advance();
        });

        $progressBar->finish();
        $output->writeln('');

        $io->table(
            ['Processed', 'Successful', 'Skipped', 'Failed'],
            [[$result->getProcessed(), $result->getSuccessful(), count($result->getSkipped()), count($result->getFailures())]],
        );

        if ($skipped = $result->getSkipped()) {
            $io->section('Skipped rows');
            $io->listing($skipped);
        }

        if ($failures = $result->getFailures()) {
            $io->section('Failed rows');
            $io->listing($failures);
        }

        return Command::SUCCESS;
    }
}
