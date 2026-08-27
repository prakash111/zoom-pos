<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute必须被接受。',
    'accepted_if' => '当:other为:value时，:attribute必须被接受。',
    'active_url' => ':attribute必须是一个有效的网址。',
    'after' => ':attribute必须是:date之后的日期。',
    'after_or_equal' => ':attribute必须是:date之后或与之相同的日期。',
    'alpha' => ':attribute只能包含字母。',
    'alpha_dash' => ':attribute只能包含字母、数字、破折号和下划线。',
    'alpha_num' => ':attribute只能包含字母和数字。',
    'any_of' => ':attribute无效。',
    'array' => ':attribute必须是一个数组。',
    'array_keys' => ':attribute只能包含以下键：:values。',
    'ascii' => ':attribute只能包含单字节的字母数字字符和符号。',
    'base64' => ':attribute必须是有效的Base64字符串。',
    'before' => ':attribute必须是:date之前的日期。',
    'before_or_equal' => ':attribute必须是:date之前或与之相同的日期。',
    'between' => [
        'array' => ':attribute必须有:min到:max个项目。',
        'file' => ':attribute必须在:min到:max KB之间。',
        'numeric' => ':attribute必须在:min到:max之间。',
        'string' => ':attribute必须在:min到:max个字符之间。',
    ],
    'boolean' => ':attribute字段必须是true或false。',
    'can' => ':attribute字段包含未授权的值。',
    'confirmed' => ':attribute的确认不匹配。',
    'contains' => ':attribute缺少必需的值。',
    'current_password' => '密码不正确。',
    'date' => ':attribute不是一个有效的日期。',
    'date_equals' => ':attribute必须是与:date相同的日期。',
    'date_format' => ':attribute的格式必须为:format。',
    'decimal' => ':attribute必须有:decimal位小数。',
    'declined' => ':attribute必须被拒绝。',
    'declined_if' => '当:other为:value时，:attribute必须被拒绝。',
    'different' => ':attribute和:other必须不同。',
    'digits' => ':attribute必须是:digits位数字。',
    'digits_between' => ':attribute必须在:min到:max位数字之间。',
    'dimensions' => ':attribute图片尺寸无效。',
    'distinct' => ':attribute有重复的值。',
    'doesnt_contain' => ':attribute不能包含以下任何内容：:values。',
    'doesnt_end_with' => ':attribute不能以以下任何内容结尾：:values。',
    'doesnt_start_with' => ':attribute不能以以下任何内容开头：:values。',
    'email' => ':attribute必须是一个有效的邮箱地址。',
    'encoding' => ':attribute必须使用:encoding编码。',
    'ends_with' => ':attribute必须以以下之一结尾：:values。',
    'enum' => '所选的:attribute无效。',
    'exists' => '所选的:attribute无效。',
    'extensions' => ':attribute必须具有以下扩展名之一：:values。',
    'file' => ':attribute必须是一个文件。',
    'filled' => ':attribute字段必须有一个值。',
    'gt' => [
        'array' => ':attribute必须多于:value个项目。',
        'file' => ':attribute必须大于:value KB。',
        'numeric' => ':attribute必须大于:value。',
        'string' => ':attribute必须多于:value个字符。',
    ],
    'gte' => [
        'array' => ':attribute必须大于等于:value个项目。',
        'file' => ':attribute必须大于或等于:value KB。',
        'numeric' => ':attribute必须大于或等于:value。',
        'string' => ':attribute必须大于或等于:value个字符。',
    ],
    'hex_color' => ':attribute必须是有效的十六进制颜色。',
    'image' => ':attribute必须是一张图片。',
    'in' => '所选的:attribute无效。',
    'in_array' => ':attribute字段必须存在于:other中。',
    'in_array_keys' => ':attribute必须至少包含以下键之一：:values。',
    'integer' => ':attribute必须是一个整数。',
    'ip' => ':attribute必须是一个有效的IP地址。',
    'ipv4' => ':attribute必须是一个有效的IPv4地址。',
    'ipv6' => ':attribute必须是一个有效的IPv6地址。',
    'json' => ':attribute必须是有效的JSON字符串。',
    'list' => ':attribute必须是一个列表。',
    'lowercase' => ':attribute必须为小写。',
    'lt' => [
        'array' => ':attribute必须少于:value个项目。',
        'file' => ':attribute必须小于:value KB。',
        'numeric' => ':attribute必须小于:value。',
        'string' => ':attribute必须少于:value个字符。',
    ],
    'lte' => [
        'array' => ':attribute不能多于:value个项目。',
        'file' => ':attribute必须小于或等于:value KB。',
        'numeric' => ':attribute必须小于或等于:value。',
        'string' => ':attribute必须小于或等于:value个字符。',
    ],
    'mac_address' => ':attribute必须是有效的MAC地址。',
    'max' => [
        'array' => ':attribute不能多于:max个项目。',
        'file' => ':attribute不能大于:max KB。',
        'numeric' => ':attribute不能大于:max。',
        'string' => ':attribute不能多于:max个字符。',
    ],
    'max_digits' => ':attribute不能多于:max位数字。',
    'mimes' => ':attribute必须是以下类型的文件：:values。',
    'mimetypes' => ':attribute必须是以下类型的文件：:values。',
    'min' => [
        'array' => ':attribute至少要有:min个项目。',
        'file' => ':attribute大小至少为:min KB。',
        'numeric' => ':attribute至少为:min。',
        'string' => ':attribute至少要有:min个字符。',
    ],
    'min_digits' => ':attribute至少要有:min位数字。',
    'missing' => ':attribute字段必须缺失。',
    'missing_if' => '当:other为:value时，:attribute字段必须缺失。',
    'missing_unless' => '除非:other为:value，否则:attribute字段必须缺失。',
    'missing_with' => '当:values存在时，:attribute字段必须缺失。',
    'missing_with_all' => '当:values都存在时，:attribute字段必须缺失。',
    'multiple_of' => ':attribute必须是:value的倍数。',
    'not_in' => '所选的:attribute无效。',
    'not_regex' => ':attribute格式无效。',
    'numeric' => ':attribute必须是一个数字。',
    'password' => [
        'letters' => ':attribute必须包含至少一个字母。',
        'mixed' => ':attribute必须包含至少一个大写字母和一个小写字母。',
        'numbers' => ':attribute必须包含至少一个数字。',
        'symbols' => ':attribute必须包含至少一个符号。',
        'uncompromised' => '给定的:attribute已出现在数据泄露中，请选择其他:attribute。',
    ],
    'present' => ':attribute字段必须存在。',
    'present_if' => '当:other为:value时，:attribute字段必须存在。',
    'present_unless' => '除非:other为:value，否则:attribute字段必须存在。',
    'present_with' => '当:values存在时，:attribute字段必须存在。',
    'present_with_all' => '当:values都存在时，:attribute字段必须存在。',
    'prohibited' => ':attribute字段被禁止。',
    'prohibited_if' => '当:other为:value时，:attribute字段被禁止。',
    'prohibited_if_accepted' => '当:other被接受时，:attribute字段被禁止。',
    'prohibited_if_declined' => '当:other被拒绝时，:attribute字段被禁止。',
    'prohibited_unless' => '除非:other在:values中，否则:attribute字段被禁止。',
    'prohibits' => ':attribute字段禁止:other存在。',
    'regex' => ':attribute格式无效。',
    'required' => ':attribute字段是必填的。',
    'required_array_keys' => ':attribute字段必须包含以下条目：:values。',
    'required_if' => '当:other为:value时，:attribute字段是必填的。',
    'required_if_accepted' => '当:other被接受时，:attribute字段是必填的。',
    'required_if_declined' => '当:other被拒绝时，:attribute字段是必填的。',
    'required_unless' => '除非:other在:values中，否则:attribute字段是必填的。',
    'required_with' => '当:values存在时，:attribute字段是必填的。',
    'required_with_all' => '当:values都存在时，:attribute字段是必填的。',
    'required_without' => '当:values不存在时，:attribute字段是必填的。',
    'required_without_all' => '当:values都不存在时，:attribute字段是必填的。',
    'same' => ':attribute和:other必须匹配。',
    'size' => [
        'array' => ':attribute必须包含:size个项目。',
        'file' => ':attribute大小必须为:size KB。',
        'numeric' => ':attribute必须为:size。',
        'string' => ':attribute必须为:size个字符。',
    ],
    'starts_with' => ':attribute必须以以下之一开头：:values。',
    'string' => ':attribute必须是一个字符串。',
    'timezone' => ':attribute必须是一个有效的时区。',
    'unique' => ':attribute已经被占用。',
    'uploaded' => ':attribute上传失败。',
    'uppercase' => ':attribute必须为大写。',
    'url' => ':attribute必须是一个有效的网址。',
    'ulid' => ':attribute必须是有效的ULID。',
    'uuid' => ':attribute必须是有效的UUID。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
