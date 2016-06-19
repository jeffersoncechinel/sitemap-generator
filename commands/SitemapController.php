<?php

namespace app\commands;

use yii\console\Controller;
use yii\helpers\Console;
use Yii;
use XMLWriter;
use app\models\Sitemap;
use GuzzleHttp\Client;

class SitemapController extends Controller
{

    private $lastUpdateKey = 'sitemap-content-last-update';
    private $webRoot;


    public function actionIndex()
    {
        echo "Usage: yii sitemap/init\n";
        echo "Usage: yii sitemap/init now\n";

        return 0;
    }

    public function actionInit($now = null, $static = null)
    {
        $totalTasks = 5;
        Console::startProgress(0, $totalTasks, 'Counting objects: ', false);

        if (!$now == 'now') {
            $lastUpdate = Yii::$app->cache->get($this->lastUpdateKey);
            $currentLast = Yii::$app->cache->get($this->lastUpdateKey);
            //$currentLast = (new Query())->select('max(updated_at)')->from('content')->scalar();

            if ($lastUpdate == $currentLast) {
                echo("\nAborting. No update needed\n\n");
                return;
            }
            Console::updateProgress(1, $totalTasks);

            Yii::$app->cache->set($this->lastUpdateKey, $currentLast);
        }

        $beginTime = microtime(true);
        $this->webRoot = \Yii::$app->params['webRoot'];

        //CREATING CONTENT INDEX
        Console::updateProgress(2, $totalTasks);
        $sitemaps = Sitemap::findAll(['is_active' => 1, 'is_child' => 1, 'is_static' => 0]);
        foreach ($sitemaps as $sitemap) {
            $this->generateContentIndex($sitemap);
        }

        //CREATING SITE INDEX
        if ($static == 'update-all') {
            Console::updateProgress(3, $totalTasks);
            $sitemaps = Sitemap::findAll(['is_active' => 1, 'is_child' => 1, 'is_static' => 1]);
            foreach ($sitemaps as $sitemap) {
                $this->generateSiteIndex($sitemap);
            }
        }

        //CREATING SITEMAP
        Console::updateProgress(4, $totalTasks);
        $sitemaps = Sitemap::findAll(['is_active' => 1, 'is_child' => 0]);
        foreach ($sitemaps as $sitemap) {
            $this->generateSitemap($sitemap);
        }

        //INFORMING GOOGLE ABOUT THE NEW SITEMAP
        Console::updateProgress(5, $totalTasks);
        //$this->pingGoogle(\Yii::$app->params['url']);

        Console::endProgress();
        $duration = microtime(true) - $beginTime;
        echo("Script generated in {$duration} seconds.\n\n");
    }

    private function generateSitemap($sitemap)
    {
        $extension = '';
        $sitemapFile = $this->webRoot . DIRECTORY_SEPARATOR . $sitemap->filename;

        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $children = Sitemap::findAll(['is_active' => 1, 'is_child' => 1]);
        foreach ($children as $child) {
            if ($child->is_compressed == 1) {
                $extension = '.gz';
            }

            $path = $this->webRoot . DIRECTORY_SEPARATOR . $child->filename . $extension;

            if (file_exists($path)) {
                $writer->startElement('sitemap');
                $writer->writeElement('loc', \Yii::$app->params['url'] . $child->filename . $extension);
                $writer->writeElement('lastmod', date('c', filemtime($path)));
                $writer->endElement();
            }
        }

        $writer->endElement();
        $writer->endDocument();

        if ($sitemap->is_compressed == 1) {
            $gzfile = $sitemapFile . '.gz';
            $fp = gzopen($gzfile, 'w9');
            gzwrite($fp, $writer->outputMemory(true));
            gzclose($fp);
        } else {
            $fp = fopen($sitemapFile, 'c');
            fwrite($fp, $writer->outputMemory(true));
            fclose($fp);
        }
    }

    private function generateContentIndex($sitemap)
    {
        $sitemapFile = $this->webRoot . DIRECTORY_SEPARATOR . $sitemap->filename;
        $writer = new XMLWriter();

        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');

        //GETTING SERVICES
        $services = $this->getServices();
        foreach ($services as $row) {
            $writer->startElement('url');
            $writer->writeElement('loc', \Yii::$app->params['url'] . $row['service_slug']);
            $writer->writeElement('priority', $sitemap['priority']);
            $writer->writeElement('changefreq', $sitemap['changefreq']);

            if (file_exists($this->webRoot . DIRECTORY_SEPARATOR . $sitemap['filename'])) {
                $writer->writeElement('lastmod', date('c', filemtime($this->webRoot . DIRECTORY_SEPARATOR . $sitemap['filename'])));
            } else {
                $writer->writeElement('lastmod', $this->getNow());
            }
            $writer->endElement();
        }

        //GETTING LANDING PAGES
        $slugs = $this->getSlugs();
        foreach ($slugs as $row) {
            $writer->startElement('url');
            $writer->writeElement('loc', \Yii::$app->params['url'] . $row['url_slug']);
            $writer->writeElement('priority', $sitemap['priority']);
            $writer->writeElement('changefreq', $sitemap['changefreq']);

            if (file_exists($this->webRoot . DIRECTORY_SEPARATOR . $sitemap['filename'])) {
                $writer->writeElement('lastmod', date('c', filemtime($this->webRoot . DIRECTORY_SEPARATOR . $sitemap['filename'])));
            } else {
                $writer->writeElement('lastmod', $this->getNow());
            }
            $writer->endElement();
        }

        //GETTING STATE SERVICES
        $servicesStates = $this->getStates();
        foreach ($services as $service) {
            foreach ($servicesStates as $row) {
                $writer->startElement('url');
                $writer->writeElement('loc', \Yii::$app->params['url'] . $service['service_slug'] . DIRECTORY_SEPARATOR . $row['prefix_state']);
                $writer->writeElement('priority', $sitemap['priority']);
                $writer->writeElement('changefreq', $sitemap['changefreq']);

                if (file_exists($this->webRoot . DIRECTORY_SEPARATOR . $sitemap['filename'])) {
                    $writer->writeElement('lastmod', date('c', filemtime($this->webRoot . DIRECTORY_SEPARATOR . $sitemap['filename'])));
                } else {
                    $writer->writeElement('lastmod', $this->getNow());
                }
                $writer->endElement();
            }
        }

        $writer->endElement();
        $writer->endDocument();

        if ($sitemap->is_compressed == 1) {
            $gzfile = $sitemapFile . '.gz';
            $fp = gzopen($gzfile, 'w9');
            gzwrite($fp, $writer->outputMemory(true));
            gzclose($fp);
        } else {
            $fp = fopen($sitemapFile, 'c');
            fwrite($fp, $writer->outputMemory(true));
            fclose($fp);
        }
    }

    private function generateSiteIndex($sitemap)
    {
        $sitemapFile = $this->webRoot . DIRECTORY_SEPARATOR . $sitemap->filename;
        $writer = new XMLWriter();

        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');

        $urls = [
            'https://www.treemenu.net/services',
            'https://signup.treemenu.net/',
            'https://www.treemenu.net/about-us',
            'https://www.treemenu.net/terms-and-conditions',
            'https://www.treemenu.net/privacy-policy',
            'https://www.treemenu.net/contact-us',
            'https://www.treemenu.net/austin-tx',
            'https://www.treemenu.net/chicago-il',
            'https://www.treemenu.net/dallas-tx',
            'https://www.treemenu.net/detroit-mi',
            'https://www.treemenu.net/houston-tx',
            'https://www.treemenu.net/indianapolis-in',
            'https://www.treemenu.net/jacksonville-fl',
            'https://www.treemenu.net/los-angeles-ca',
            'https://www.treemenu.net/new-york-ny',
            'https://www.treemenu.net/philadelphia-pa',
            'https://www.treemenu.net/phoenix-az',
            'https://www.treemenu.net/san-antonio-nm',
            'https://www.treemenu.net/san-diego-ca',
            'https://www.treemenu.net/san-francisco-ca',
            'https://www.treemenu.net/san-jose-ca',
        ];

        foreach ($urls as $row) {
            $writer->startElement('url');
            $writer->writeElement('loc', $row);
            $writer->writeElement('priority', $sitemap['priority']);
            $writer->writeElement('changefreq', $sitemap['changefreq']);

            if (file_exists($this->webRoot . DIRECTORY_SEPARATOR . $sitemap['filename'])) {
                $writer->writeElement('lastmod', date('c', filemtime($this->webRoot . DIRECTORY_SEPARATOR . $sitemap['filename'])));
            } else {
                $writer->writeElement('lastmod', $this->getNow());
            }
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        if ($sitemap->is_compressed == 1) {
            $gzfile = $sitemapFile . '.gz';
            $fp = gzopen($gzfile, 'w9');
            gzwrite($fp, $writer->outputMemory(true));
            gzclose($fp);
        } else {
            $fp = fopen($sitemapFile, 'c');
            fwrite($fp, $writer->outputMemory(true));
            fclose($fp);
        }
    }

    private function pingGoogle($url)
    {
        $client = new Client();
        $response = $client->get('http://www.google.com/webmasters/tools/ping?sitemap=https%3A%2F%2F' . $url . '%2Fsitemap.xml');
    }

    private function getServices()
    {
        $connection = Yii::$app->getDb();
        $command = $connection->createCommand('SELECT * from services where active_service = 1');
        $result = $command->queryAll();

        return $result;
    }

    private function getStates()
    {
        $connection = Yii::$app->getDb();
        $command = $connection->createCommand('SELECT * from w_coverage_cities group by prefix_state');
        $result = $command->queryAll();

        return $result;
    }

    private function getSlugs()
    {
        $connection = Yii::$app->getDb();
        $command = $connection->createCommand('SELECT * from slugs');
        $result = $command->queryAll();

        return $result;
    }

    private function getNow()
    {
        $now = new \DateTime('now');

        return $now->format('Y-m-d\TH:i:sP');
    }

    public function getLastUpdate()
    {
        /* we need to make an implementation on this */
    }

}