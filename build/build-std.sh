#!/bin/bash
if [ $1 ] 
then
cd ../..
# Create build directory if not already exist
mkdir -p build
rm -rf build/wishlistcast
rm -rf build/wishlistcast_v$1.zip
# Copy all wishlistcastpro files to temporary build directory
find wishlistcastpro -depth -print | cpio -pvd build
# Rename temp build directory
mv build/wishlistcastpro build/wishlistcast
cd build
sed -e "s/Plugin Name: WishList API Pro/Plugin Name: WishList API/" wishlistcast/wishlistcast.php > wishlistcast.tmp
rm -rf wishlistcast/wishlistcast.php
mv wishlistcast.tmp wishlistcast/wishlistcast.php
find wishlistcast -type f | grep -v "wishlistcastpro" | grep -v "\.svn" | grep -v ".git" | grep -v ".gitignore" | grep -v "\.DS_Store" | grep -v "nbproject/.*" | grep -v "replay/.*" | grep -v "wishlistcast.log" | grep -v "params_.*" | grep -v "samcart_api.php" | grep -v "build/.*" | grep -v "nanacast-setting.png" | grep -v ".sslspkg" | zip wishlistcast_v$1.zip -@
cd ..
rm -rf build/wishlistcast
exit 0
fi
echo "Usage: build-std <version>"
echo "Example: build-std 1.4.5"
exit 0