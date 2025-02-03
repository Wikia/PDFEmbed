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
 */
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;

class PDFEmbed implements ParserFirstCallInitHook {
	public function __construct( private readonly Config $config, private readonly RepoGroup $repoGroup ) {
	}

	/**
	 * Sets up this extension's parser functions.
	 *
	 * @param object $parser Parser object passed as a reference.
	 * @return bool true
	 */
	public function onParserFirstCallInit( $parser ): bool {
		$parser->setHook( 'pdf', [ $this, 'generateTag' ] );

		return true;
	}

	/**
	 * Generates the PDF object tag.
	 *
	 * @param string $file Namespace prefixed article of the PDF file to display.
	 * @param array $args Arguments on the tag.
	 * @param Parser $parser Parser object.
	 * @param PPFrame $frame PPFrame object.
	 * @return string HTML
	 */
	public function generateTag( string $file, array $args, Parser $parser, PPFrame $frame ): string {
		$request = RequestContext::getMain()->getRequest();
		$wgPdfEmbed = $this->config->get( 'PdfEmbed' );

		if ( str_contains( $file, '{{{' ) ) {
			$file = $parser->recursiveTagParse( $file, $frame );
		}

		if ( $request->getVal( 'action' ) == 'edit' || $request->getVal( 'action' ) == 'submit' ) {
			$user = RequestContext::getMain()->getUser();
		} else {
			$userFactory = MediaWikiServices::getInstance()->getUserFactory();
			$user = $userFactory->newFromName( $parser->getRevisionUser() );
		}

		if ( $user === false ) {
			return self::error( 'embed_pdf_invalid_user' );
		}

		if ( !$user->isAllowed( 'embed_pdf' ) ) {
			return self::error( 'embed_pdf_no_permission' );
		}

		if ( empty( $file ) || !preg_match( '#(.+?)\.pdf#is', $file ) ) {
			return self::error( 'embed_pdf_blank_file' );
		}

		$file = $this->repoGroup->findFile( Title::newFromText( $file ) );

		if ( array_key_exists( 'width', $args ) ) {
			$width = intval( $parser->recursiveTagParse( $args['width'], $frame ) );
		} else {
			$width = intval( $wgPdfEmbed['width'] );
		}
		if ( array_key_exists( 'height', $args ) ) {
			$height = intval( $parser->recursiveTagParse( $args['height'], $frame ) );
		} else {
			$height = intval( $wgPdfEmbed['height'] );
		}
		if ( array_key_exists( 'page', $args ) ) {
			$page = intval( $parser->recursiveTagParse( $args['page'], $frame ) );
		} else {
			$page = 1;
		}

		if ( $file !== false ) {
			return self::embed( $file, $width, $height, $page );
		} else {
			return self::error( 'embed_pdf_invalid_file' );
		}
	}

	/**
	 * Returns a standard error message.
	 *
	 * @private
	 * @param string $messageKey Error message key to display.
	 * @return string HTML error message.
	 */
	private static function error( string $messageKey ): string {
		return Html::element( 'span', [ 'class' => 'error' ], wfMessage( $messageKey )->plain() );
	}

	/**
	 * Returns a HTML object as string.
	 *
	 * @private
	 * @param File $file File object.
	 * @param integer $width Width of the object.
	 * @param integer $height Height of the object.
	 * @param int $page
	 * @return string HTML object.
	 */
	private static function embed( File $file, int $width, int $height, int $page ): string {
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
