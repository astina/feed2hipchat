<?php

namespace Astina\Feed2HipChat;

use Guzzle\Http\Client;
use HipChat\HipChat;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

class CheckFeedCommand extends Command
{
    private $config;

    protected function configure()
    {
        $this
            ->setName('feed:check')
            ->addArgument('url', InputArgument::REQUIRED, 'Feed URL')
            ->addOption('application', null, InputOption::VALUE_OPTIONAL, 'Name of the monitored application (name displayed in HipChat channel)', 'RSS Feed')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Config file', __DIR__ . '/../../../config.yml')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $feedUrl = $input->getArgument('url');
        $configFile = $input->getOption('config');
        $application = $input->getOption('application');
        $this->loadConfig($configFile);

        $feedItems = $this->fetchItems($feedUrl);

        foreach ($feedItems as $feedItem) {
            $output->writeln(sprintf('Checking item: "<info>%s</info>" [<comment>%s</comment>]', (strlen($feedItem->title) > 80 ? substr($feedItem->title, 0, 76) . ' ...' : $feedItem->title), $feedItem->guid));
            if ($this->isNewItem($feedItem)) {
                $output->writeln(sprintf('  > <info>Sending alert for "%s" to HipChat channel</info> <comment>#%s</comment>', $application, $this->config['hipchat_channel_id']));
                $this->sendAlert($feedItem, $application);
            }
        }

//        $output->write('Cleanup ...');
//        $this->cleanup();
//        $output->writeln(' done');
    }

    private function loadConfig($file)
    {;
        $this->config = Yaml::parse($file) + array('cache_dir' => __DIR__ . '/../../../cache');
    }

    private function fetchItems($feedUrl)
    {
        $client = new Client();
        try {
            $xml = $client->get($feedUrl)->send()->xml();
        } catch (\Exception $e) {
            throw new \Exception('Failed to fetch feed items for URL: ' . $feedUrl, null, $e);
        }

        $items = array();
        foreach ($xml->channel->item as $item) {
            $items[] = $item;
        }

        return $items;
    }

    private function sendAlert($feedItem, $application)
    {
        $client = new HipChat($this->config['hipchat_auth_token']);

        $message = sprintf("%s\n%s\n\n%s", $feedItem->title, $feedItem->link, $feedItem->description);
        $client->message_room(
            $this->config['hipchat_channel_id'],
            $application,
            $message,
            true, // notify
            HipChat::COLOR_RED,
            HipChat::FORMAT_TEXT
        );

        $this->rememberItem($feedItem);
    }

    private function isNewItem($feedItem)
    {
        return !file_exists($this->getCacheFileName($feedItem));
    }

    private function rememberItem($feedItem)
    {
        file_put_contents($this->getCacheFileName($feedItem), $feedItem->guid, LOCK_EX);
    }

    private function getCacheFileName($feedItem)
    {
        $dir = $this->config['cache_dir'];

        return $dir . '/' . preg_replace('/[^a-z0-9-_.]/', '-', $feedItem->guid);
    }

    private function cleanup()
    {
        $dir = $this->config['cache_dir'];

        $finder = new Finder();
        /** @var SplFileInfo $file */
        foreach ($finder->in($dir)->date('until 1 month ago') as $file) {
            unlink($file->getRealPath());
        }
    }
} 