<?php

namespace OpenDemat\Core\Command;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'open-demat:minio:test',
    description: 'Teste la connexion Symfony -> MinIO (write/read/delete)'
)]
final class MinioTestCommand extends Command
{
    public function __construct(
        private readonly FilesystemOperator $documentsStorage, // @documents.storage
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = 'symfony-test/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt';
        $content = 'hello from symfony ' . date(DATE_ATOM);

        $output->writeln("Writing: <info>$key</info>");
        $this->documentsStorage->write($key, $content);

        $output->writeln("Reading: <info>$key</info>");
        $read = $this->documentsStorage->read($key);

        if ($read !== $content) {
            $output->writeln('<error>Content mismatch</error>');
            return Command::FAILURE;
        }

        $output->writeln("Deleting: <info>$key</info>");
        $this->documentsStorage->delete($key);

        $output->writeln('<info>OK: Symfony <-> MinIO fonctionne</info>');
        return Command::SUCCESS;
    }
}
