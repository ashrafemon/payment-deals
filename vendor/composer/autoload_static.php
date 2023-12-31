<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit067303416ccda23e8152c4bb7e6caf3a
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'LeafWrap\\PaymentDeals\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'LeafWrap\\PaymentDeals\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit067303416ccda23e8152c4bb7e6caf3a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit067303416ccda23e8152c4bb7e6caf3a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit067303416ccda23e8152c4bb7e6caf3a::$classMap;

        }, null, ClassLoader::class);
    }
}
