<?php
	class WordpressToJSX {
	  private $input;
	
	  public function __construct($input) {
		$this->input = $input;
	  }
	
	  public function convert() {
		$converted = '';
		$blocks = $this->parseBlocks();
	
		foreach ($blocks as $block) {
		  $converted .= $this->convertBlock($block);
		}
	
		return $converted;
	  }
	
	  private function parseBlocks() {
		$pattern = '/<!-- wp:([a-z-]+)(.*?) -->\n?(.*?)<!-- \/wp:[a-z-]+ -->/s';
		preg_match_all($pattern, $this->input, $matches, PREG_SET_ORDER);
	
		$blocks = array();
		foreach ($matches as $match) {
		  $block = array(
			'name' => $match[1],
			'attrs' => $this->parseAttributes($match[2]),
			'content' => $match[3],
		  );
		  $blocks[] = $block;
		}
	
		return $blocks;
	  }
	
	  private function parseAttributes($attrString) {
		$attributes = array();
		$pattern = '/(\w+)\s*=\s*"([^"]+)"/';
		preg_match_all($pattern, $attrString, $matches, PREG_SET_ORDER);
	
		foreach ($matches as $match) {
		  $attributes[$match[1]] = $match[2];
		}
	
		return $attributes;
	  }
	
	  private function convertBlock($block) {
		$blockName = $block['name'];
		$blockAttrs = $block['attrs'];
		$blockContent = $block['content'];
	
		switch ($blockName) {
		  case 'paragraph':
			return '<Text>' . $blockContent . '</Text>' . "\n";
		  case 'heading':
			$level = isset($blockAttrs['level']) ? $blockAttrs['level'] : 1;
			return '<Heading' . $level . '>' . $blockContent . '</Heading' . $level . '>' . "\n";
		  case 'list':
			return $this->convertList($blockContent);
		  case 'html':
			return $blockContent . "\n";
		  case 'image':
			$imageId = isset($blockAttrs['id']) ? $blockAttrs['id'] : '';
			$imageUrl = $this->getImageUrl($imageId);
			$altText = isset($blockAttrs['alt']) ? $blockAttrs['alt'] : '';
	
			return '<Image source={{uri: "' . $imageUrl . '"}} alt="' . $altText . '" />' . "\n";
		  default:
			return '';
		}
	  }
	
	  private function convertList($listContent) {
		$pattern = '/<!-- wp:list-item -->\n?(.*?)<!-- \/wp:list-item -->/s';
		preg_match_all($pattern, $listContent, $matches, PREG_SET_ORDER);
	
		$listItems = array();
		foreach ($matches as $match) {
		  $listItems[] = $match[1];
		}
	
		$listItems = array_map(function ($item) {
		  return '<Text>- ' . $item . '</Text>';
		}, $listItems);
	
		return '<View>' . implode("\n", $listItems) . '</View>' . "\n";
	  }
	
	  private function getImageUrl($imageId) {
		return 'https://example.com/images/' . $imageId;
	  }
	}
?>