<?php

$finder = PhpCsFixer\Finder::create()
    ->in(dirs: [__DIR__ . '/src', __DIR__ . '/tests', ])
;

//to get information by the link you need to replace '2.17' with actual version of phpcs-fixer
return (new PhpCsFixer\Config())
    ->setRules(rules: [
        '@Symfony' => true,
        'array_push' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/alias/array_push.rst
        'set_type_to_cast' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/alias/set_type_to_cast.rst
        'array_syntax' => ['syntax' => 'short'], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/array_notation/array_syntax.rst
        'modernize_types_casting' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/cast_notation/modernize_types_casting.rst
        'class_definition' => ['multi_line_extends_each_single_line' => true], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/class_notation/class_definition.rst
        'final_class' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/class_notation/final_class.rst
        'final_public_method_for_abstract_class' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/class_notation/final_public_method_for_abstract_class.rst
        'no_unneeded_final_method' => ['private_methods' => true], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/class_notation/no_unneeded_final_method.rst
        'ordered_traits' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/class_notation/ordered_traits.rst
        'protected_to_private' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/class_notation/protected_to_private.rst
        'self_static_accessor' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/class_notation/self_static_accessor.rst
        'no_superfluous_elseif' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/control_structure/no_superfluous_elseif.rst
        'no_useless_else' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/control_structure/no_useless_else.rst
        'simplified_if_return' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/control_structure/simplified_if_return.rst
        'implode_call' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/function_notation/implode_call.rst
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/function_notation/method_argument_space.rst
        'no_useless_sprintf' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/function_notation/no_useless_sprintf.rst
        'single_line_throw' => false, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/function_notation/single_line_throw.rst
        'void_return' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/function_notation/void_return.rst
        'combine_consecutive_issets' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/language_construct/combine_consecutive_issets.rst
        'combine_consecutive_unsets' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/language_construct/combine_consecutive_unsets.rst
        'declare_equal_normalize' => ['space' => 'single'], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/language_construct/declare_equal_normalize.rst
        'error_suppression' => ['mute_deprecation_error' => false, 'noise_remaining_usages' => true], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/language_construct/error_suppression.rst
        'is_null' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/language_construct/is_null.rst
        'concat_space' => ['spacing' => 'one'], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/operator/concat_space.rst
        'phpdoc_to_comment' => false, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.18/doc/rules/phpdoc/phpdoc_to_comment.rst

        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'allow_unused_params' => false], //
        'phpdoc_add_missing_param_annotation' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/phpdoc/phpdoc_add_missing_param_annotation.rst
        'phpdoc_line_span' => ['property' => 'single', 'const' => 'single'], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/phpdoc/phpdoc_line_span.rst
        'phpdoc_no_empty_return' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/phpdoc/phpdoc_no_empty_return.rst
        'phpdoc_var_annotation_correct_order' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/phpdoc/phpdoc_var_annotation_correct_order.rst
        'no_useless_return' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/return_notation/no_useless_return.rst
        'simplified_null_return' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/return_notation/simplified_null_return.rst
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/semicolon/multiline_whitespace_before_semicolons.rst
        'declare_strict_types' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/strict/declare_strict_types.rst
        'array_indentation' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/whitespace/array_indentation.rst
        'compact_nullable_typehint' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/whitespace/compact_nullable_typehint.rst
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']], //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/master/doc/rules/control_structure/trailing_comma_in_multiline.rst
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => null, 'import_functions' => null], //https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/rules/import/global_namespace_import.rst

        //phpunit
        'php_unit_construct' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/php_unit/php_unit_construct.rst
        'php_unit_expectation' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/php_unit/php_unit_expectation.rst
        'php_unit_mock' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/php_unit/php_unit_mock.rst
        'php_unit_no_expectation_annotation' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/php_unit/php_unit_no_expectation_annotation.rst
        'php_unit_set_up_tear_down_visibility' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/php_unit/php_unit_set_up_tear_down_visibility.rst
        'php_unit_strict' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/php_unit/php_unit_strict.rst
        'php_unit_test_case_static_method_calls' => true, //https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/rules/php_unit/php_unit_test_case_static_method_calls.rst
    ])
    ->setCacheFile(cacheFile: '/var/www/.tooling/.php-cs-fixer.cache')
    ->setFinder(finder: $finder)
;
