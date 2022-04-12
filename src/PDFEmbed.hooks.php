<?php
/**
 * PDFEmbed
 * PDFEmbed Hooks
 *
 * @author        Alexia E. Smith
 * @license        LGPLv3 http://opensource.org/licenses/lgpl-3.0.html
 * @package        PDFEmbed
 * @link        http://www.mediawiki.org/wiki/Extension:PDFEmbed
 *
 **/

use MediaWiki\MediaWikiServices;

class PDFEmbed
{
    /**
     * Sets up this extension's parser functions.
     *
     * @access    public
     * @param object    Parser object passed as a reference.
     * @return    boolean    true
     */
    static public function onParserFirstCallInit(Parser $parser)
    {
        $parser->setHook('pdf', [self::class, 'generateTag']);

        return true;
    }

    /**
     * Generates the PDF object tag.
     *
     * @access    public
     * @param string    Namespace prefixed article of the PDF file to display.
     * @param array    Arguments on the tag.
     * @param object    Parser object.
     * @param object    PPFrame object.
     * @return    string    HTML
     */
    static public function generateTag($file, $args = [], Parser $parser, PPFrame $frame)
    {
        $request = RequestContext::getMain()->getRequest();

        $services = MediaWikiServices::getInstance();
        $config = $services->getMainConfig();
        $wgPdfEmbed = $config->get('PdfEmbed');

        if (strstr($file, '{{{') !== false) {
            $file = $parser->recursiveTagParse($file, $frame);
        }

        if ($request->getVal('action') == 'edit' || $request->getVal('action') == 'submit') {
            $user = RequestContext::getMain()->getUser();
        } else {
            $userFactory = MediaWikiServices::getInstance()->getUserFactory();
            $user = $userFactory->newFromName($parser->getRevisionUser());
        }

        if ($user === false) {
            return self::error('embed_pdf_invalid_user');
        }

        if (!$user->isAllowed('embed_pdf')) {
            return self::error('embed_pdf_no_permission');
        }

        if (empty($file) || !preg_match('#(.+?)\.pdf#is', $file)) {
            return self::error('embed_pdf_blank_file');
        }

        $file = $services->getRepoGroup()->findFile(Title::newFromText($file));

        if (array_key_exists('width', $args)) {
            $width = intval($parser->recursiveTagParse($args['width'], $frame));
        } else {
            $width = intval($wgPdfEmbed['width']);
        }
        if (array_key_exists('height', $args)) {
            $height = intval($parser->recursiveTagParse($args['height'], $frame));
        } else {
            $height = intval($wgPdfEmbed['height']);
        }
        if (array_key_exists('page', $args)) {
            $page = intval($parser->recursiveTagParse($args['page'], $frame));
        } else {
            $page = 1;
        }

        if ($file !== false) {
            return self::embed($file, $width, $height, $page);
        } else {
            return self::error('embed_pdf_invalid_file');
        }
    }

    /**
     * Returns a standard error message.
     *
     * @access    private
     * @param string    Error message key to display.
     * @return    string    HTML error message.
     */
    static private function error($messageKey)
    {
        return Xml::span(wfMessage($messageKey)->plain(), 'error');
    }

    /**
     * Returns a HTML object as string.
     *
     * @access    private
     * @param object    File object.
     * @param integer    Width of the object.
     * @param integer    Height of the object.
     * @return    string    HTML object.
     */
    static private function embed(File $file, $width, $height, $page)
    {
        return Html::rawElement(
            'iframe',
            [
                'width' => $width,
                'height' => $height,
                'src' => $file->getFullUrl() . '#page=' . $page,
                'style' => 'max-width: 100%;'
            ]
        );
    }
}
