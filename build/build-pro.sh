#!/bin/bash
if [ $1 ] 
then
cd ../..
mkdir -p build
rm -rf build/wishlistcastpro_v$1.zip
find wishlistcastpro -type f | grep -v "\.svn" | grep -v ".git" | grep -v ".gitignore" | grep -v "\.DS_Store" | grep -v "nbproject/.*" | grep -v "replay/.*" | grep -v "wishlistcast.log" | grep -v "params_.*" | grep -v "build/.*" | grep -v "nanacast-setting.png" | grep -v ".sslspkg" | zip build/wishlistcastpro_v$1.zip -@
exit 0
fi
echo "Usage: build-pro <version>"
echo "Example: build-pro 1.4.5"
exit 0