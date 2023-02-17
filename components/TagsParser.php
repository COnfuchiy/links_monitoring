<?php

declare(strict_types=1);


/**
 * Class TagsParser
 */
class TagsParser
{
    /**
     * @param string $html
     * @param string $tag
     * @return array
     */
    public static function extractTags(string $html, string $tag): array
    {
        if (is_array($tag)) {
            $tag = implode('|', $tag);
        }

        if ($tag === 'img' || $tag === 'link') {
            $tag_pattern =
                '@<(?P<tag>' . $tag . ')           
            (?P<attributes>\s[^>]+)?       
            \s*/?>                    
            @xsi';
        } else {
            $tag_pattern =
                '@<(?P<tag>' . $tag . ')           
            (?P<attributes>\s[^>]+)?       
            \s*>                 
            (?P<contents>.*?)         
            </(?P=tag)>             
            @xsi';
        }

        $attribute_pattern =
            '@
        (?P<name>\w+)                         
        \s*=\s*
        (
            (?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)    
            |                           
            (?P<value_unquoted>[^\s"\']+?)(?:\s+|$)            
        )
        @xsi';

        if (!preg_match_all($tag_pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $tags = [];
        foreach ($matches as $match) {
            $attributes = [];
            if (!empty($match['attributes'][0])) {
                if (preg_match_all($attribute_pattern, $match['attributes'][0], $attribute_data, PREG_SET_ORDER)) {
                    //Turn the attribute data into a name->value array
                    foreach ($attribute_data as $attr) {
                        if (!empty($attr['value_quoted'])) {
                            $value = $attr['value_quoted'];
                        } elseif (!empty($attr['value_unquoted'])) {
                            $value = $attr['value_unquoted'];
                        } else {
                            $value = '';
                        }


                        $value = html_entity_decode($value, ENT_QUOTES);

                        $attributes[$attr['name']] = $value;
                    }
                }
            }

            $tag = [
                'tag_name' => $match['tag'][0],
                'offset' => $match[0][1],
                'contents' => !empty($match['contents']) ? $match['contents'][0] : '',
                'attributes' => $attributes,
                'full_tag' => $match[0][0],
            ];

            $tags[] = $tag;
        }

        return $tags;
    }
}
