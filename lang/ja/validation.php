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

    'accepted' => ':attributeを承諾してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承諾してください。',
    'active_url' => ':attributeには有効なURLを指定してください。',
    'after' => ':attributeには:dateより後の日付を指定してください。',
    'after_or_equal' => ':attributeには:date以降の日付を指定してください。',
    'alpha' => ':attributeには文字のみを使用してください。',
    'alpha_dash' => ':attributeには文字、数字、ダッシュ、アンダースコアのみを使用してください。',
    'alpha_num' => ':attributeには文字と数字のみを使用してください。',
    'any_of' => ':attributeは無効です。',
    'array' => ':attributeは配列を指定してください。',
    'array_keys' => ':attributeには次のキーのみを含めてください：:values。',
    'ascii' => ':attributeにはシングルバイトの英数字と記号のみを使用してください。',
    'base64' => ':attributeには有効なBase64文字列を指定してください。',
    'before' => ':attributeには:dateより前の日付を指定してください。',
    'before_or_equal' => ':attributeには:date以前の日付を指定してください。',
    'between' => [
        'array' => ':attributeには:min個から:max個の項目を指定してください。',
        'file' => ':attributeには:minから:maxキロバイトのファイルを指定してください。',
        'numeric' => ':attributeには:minから:maxの値を指定してください。',
        'string' => ':attributeには:min文字から:max文字までを指定してください。',
    ],
    'boolean' => ':attributeにはtrueかfalseを指定してください。',
    'can' => ':attributeに許可されていない値が含まれています。',
    'confirmed' => ':attributeの確認が一致しません。',
    'contains' => ':attributeに必要な値が不足しています。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeには有効な日付を指定してください。',
    'date_equals' => ':attributeには:dateと同じ日付を指定してください。',
    'date_format' => ':attributeは:formatの形式と一致しません。',
    'decimal' => ':attributeは小数点以下:decimal桁で指定してください。',
    'declined' => ':attributeを拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否してください。',
    'different' => ':attributeと:otherには異なる値を指定してください。',
    'digits' => ':attributeは:digits桁で指定してください。',
    'digits_between' => ':attributeは:min桁から:max桁までの間で指定してください。',
    'dimensions' => ':attributeの画像サイズが無効です。',
    'distinct' => ':attributeに重複した値があります。',
    'doesnt_contain' => ':attributeには次のいずれも含めないでください：:values。',
    'doesnt_end_with' => ':attributeは次のいずれでも終わらないようにしてください：:values。',
    'doesnt_start_with' => ':attributeは次のいずれでも始まらないようにしてください：:values。',
    'email' => ':attributeには有効なメールアドレスを指定してください。',
    'encoding' => ':attributeは:encodingでエンコードされている必要があります。',
    'ends_with' => ':attributeは次のいずれかで終わる必要があります：:values。',
    'enum' => '選択された:attributeは無効です。',
    'exists' => '選択された:attributeは無効です。',
    'extensions' => ':attributeには次のいずれかの拡張子を指定してください：:values。',
    'file' => ':attributeにはファイルを指定してください。',
    'filled' => ':attributeには値を指定してください。',
    'gt' => [
        'array' => ':attributeには:value個より多い項目を指定してください。',
        'file' => ':attributeには:valueキロバイトより大きいファイルを指定してください。',
        'numeric' => ':attributeには:valueより大きい値を指定してください。',
        'string' => ':attributeには:value文字より多く指定してください。',
    ],
    'gte' => [
        'array' => ':attributeには:value個以上の項目を指定してください。',
        'file' => ':attributeには:valueキロバイト以上のファイルを指定してください。',
        'numeric' => ':attributeには:value以上の値を指定してください。',
        'string' => ':attributeには:value文字以上指定してください。',
    ],
    'hex_color' => ':attributeには有効な16進数カラーコードを指定してください。',
    'image' => ':attributeには画像を指定してください。',
    'in' => '選択された:attributeは無効です。',
    'in_array' => ':attributeは:other内に存在しません。',
    'in_array_keys' => ':attributeには次のキーのうち少なくとも1つを含めてください：:values。',
    'integer' => ':attributeには整数を指定してください。',
    'ip' => ':attributeには有効なIPアドレスを指定してください。',
    'ipv4' => ':attributeには有効なIPv4アドレスを指定してください。',
    'ipv6' => ':attributeには有効なIPv6アドレスを指定してください。',
    'json' => ':attributeには有効なJSON文字列を指定してください。',
    'list' => ':attributeにはリストを指定してください。',
    'lowercase' => ':attributeは小文字で指定してください。',
    'lt' => [
        'array' => ':attributeには:value個未満の項目を指定してください。',
        'file' => ':attributeには:valueキロバイト未満のファイルを指定してください。',
        'numeric' => ':attributeには:value未満の値を指定してください。',
        'string' => ':attributeには:value文字未満で指定してください。',
    ],
    'lte' => [
        'array' => ':attributeには:value個以下の項目を指定してください。',
        'file' => ':attributeには:valueキロバイト以下のファイルを指定してください。',
        'numeric' => ':attributeには:value以下の値を指定してください。',
        'string' => ':attributeには:value文字以下で指定してください。',
    ],
    'mac_address' => ':attributeには有効なMACアドレスを指定してください。',
    'max' => [
        'array' => ':attributeには:max個以下の項目を指定してください。',
        'file' => ':attributeには:maxキロバイト以下のファイルを指定してください。',
        'numeric' => ':attributeには:max以下の値を指定してください。',
        'string' => ':attributeには:max文字以下で指定してください。',
    ],
    'max_digits' => ':attributeは:max桁以下で指定してください。',
    'mimes' => ':attributeには次のタイプのファイルを指定してください：:values。',
    'mimetypes' => ':attributeには次のタイプのファイルを指定してください：:values。',
    'min' => [
        'array' => ':attributeには:min個以上の項目を指定してください。',
        'file' => ':attributeには:minキロバイト以上のファイルを指定してください。',
        'numeric' => ':attributeには:min以上の値を指定してください。',
        'string' => ':attributeには:min文字以上で指定してください。',
    ],
    'min_digits' => ':attributeは:min桁以上で指定してください。',
    'missing' => ':attributeは存在してはいけません。',
    'missing_if' => ':otherが:valueの場合、:attributeは存在してはいけません。',
    'missing_unless' => ':otherが:valueでない限り、:attributeは存在してはいけません。',
    'missing_with' => ':valuesが存在する場合、:attributeは存在してはいけません。',
    'missing_with_all' => ':valuesが存在する場合、:attributeは存在してはいけません。',
    'multiple_of' => ':attributeには:valueの倍数を指定してください。',
    'not_in' => '選択された:attributeは無効です。',
    'not_regex' => ':attributeの形式が無効です。',
    'numeric' => ':attributeには数値を指定してください。',
    'password' => [
        'letters' => ':attributeには少なくとも1つの文字を含めてください。',
        'mixed' => ':attributeには少なくとも1つの大文字と1つの小文字を含めてください。',
        'numbers' => ':attributeには少なくとも1つの数字を含めてください。',
        'symbols' => ':attributeには少なくとも1つの記号を含めてください。',
        'uncompromised' => '指定された:attributeはデータ漏洩の中で見つかりました。別の:attributeを選択してください。',
    ],
    'present' => ':attributeが存在している必要があります。',
    'present_if' => ':otherが:valueの場合、:attributeが存在している必要があります。',
    'present_unless' => ':otherが:valueでない限り、:attributeが存在している必要があります。',
    'present_with' => ':valuesが存在する場合、:attributeが存在している必要があります。',
    'present_with_all' => ':valuesが存在する場合、:attributeが存在している必要があります。',
    'prohibited' => ':attributeは許可されていません。',
    'prohibited_if' => ':otherが:valueの場合、:attributeは許可されていません。',
    'prohibited_if_accepted' => ':otherが承諾された場合、:attributeは許可されていません。',
    'prohibited_if_declined' => ':otherが拒否された場合、:attributeは許可されていません。',
    'prohibited_unless' => ':otherが:valuesに含まれていない限り、:attributeは許可されていません。',
    'prohibits' => ':attributeは:otherの存在を禁止します。',
    'regex' => ':attributeの形式が無効です。',
    'required' => ':attributeは必須です。',
    'required_array_keys' => ':attributeには次の項目のエントリが必要です：:values。',
    'required_if' => ':otherが:valueの場合、:attributeは必須です。',
    'required_if_accepted' => ':otherが承諾された場合、:attributeは必須です。',
    'required_if_declined' => ':otherが拒否された場合、:attributeは必須です。',
    'required_unless' => ':otherが:valuesに含まれていない限り、:attributeは必須です。',
    'required_with' => ':valuesが存在する場合、:attributeは必須です。',
    'required_with_all' => ':valuesが存在する場合、:attributeは必須です。',
    'required_without' => ':valuesが存在しない場合、:attributeは必須です。',
    'required_without_all' => ':valuesのいずれも存在しない場合、:attributeは必須です。',
    'same' => ':attributeは:otherと一致させてください。',
    'size' => [
        'array' => ':attributeには:size個の項目を指定してください。',
        'file' => ':attributeには:sizeキロバイトのファイルを指定してください。',
        'numeric' => ':attributeには:sizeを指定してください。',
        'string' => ':attributeは:size文字で指定してください。',
    ],
    'starts_with' => ':attributeは次のいずれかで始まる必要があります：:values。',
    'string' => ':attributeには文字列を指定してください。',
    'timezone' => ':attributeには有効なタイムゾーンを指定してください。',
    'unique' => ':attributeはすでに使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字で指定してください。',
    'url' => ':attributeには有効なURLを指定してください。',
    'ulid' => ':attributeには有効なULIDを指定してください。',
    'uuid' => ':attributeには有効なUUIDを指定してください。',

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
