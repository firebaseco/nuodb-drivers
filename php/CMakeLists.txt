# NuoDB PHP Interface
# 

cmake_minimum_required(VERSION 2.8)
project(NUOPHP)

INCLUDE_DIRECTORIES (
  ${CMAKE_CURRENT_SOURCE_DIR}
  ${NUODB_ROOT}/include
  ${PHP_INCLUDE}
  ${PHP_INCLUDE}/main
  ${PHP_INCLUDE}/TSRM
  ${PHP_INCLUDE}/Zend
  ${PHP_INCLUDE}/ext
  ${PHP_INCLUDE}/ext/date/lib
)

set (NuoDBPhpSources
    nuodb_php.cpp
    php_nuodb.h
)

IF(CMAKE_GENERATOR MATCHES "Unix Makefile")
   add_definitions(-DPHP_ATOM_INC)
   add_definitions(-DHAVE_CONFIG_H)
   add_definitions(-DHAVE_DLFCN_H=1)
   add_definitions(-std=c++0x) 
   add_library(php5ts SHARED IMPORTED)
   add_library(nuoremote SHARED IMPORTED)
   set_property(TARGET nuoremote PROPERTY IMPORTED_LOCATION ${NUODB_ROOT}/lib64/libNuoRemote.so)
   set_property(TARGET nuoremote PROPERTY IMPORTED_IMPLIB ${NUODB_ROOT}/lib64/libNuoRemote.so)
   add_library(php_nuodb SHARED ${NuoDBPhpSources})
   target_link_libraries(php_nuodb nuoremote )
ENDIF(CMAKE_GENERATOR MATCHES "Unix Makefile")

IF(CMAKE_GENERATOR MATCHES "Visual Studio 7" OR
   CMAKE_GENERATOR MATCHES "Visual Studio 8" OR
   CMAKE_GENERATOR MATCHES "Visual Studio 9" OR
   CMAKE_GENERATOR MATCHES "Visual Studio 10")
   add_definitions(-DZTS=1 -DHAVE_CONFIG_H -DZEND_DEBUG=0 -DCOMPILE_DL_NUODB -DPHP_WIN32 -DZEND_WIN32)
   add_library(php5ts SHARED IMPORTED)
   set_property(TARGET php5ts PROPERTY IMPORTED_LOCATION ${PHP_ROOT}/php5ts.dll)
   set_property(TARGET php5ts PROPERTY IMPORTED_IMPLIB ${PHP_ROOT}/SDK/lib/php5ts.lib)
   add_library(nuoremote SHARED IMPORTED)
   set_property(TARGET nuoremote PROPERTY IMPORTED_LOCATION ${NUODB_ROOT}/bin/NuoRemote.dll)
   set_property(TARGET nuoremote PROPERTY IMPORTED_IMPLIB ${NUODB_ROOT}/lib/NuoRemote.lib)
   add_library(php_nuodb SHARED ${NuoDBPhpSources})
   target_link_libraries(php_nuodb nuoremote php5ts)
ENDIF(CMAKE_GENERATOR MATCHES "Visual Studio 7" OR
      CMAKE_GENERATOR MATCHES "Visual Studio 8" OR
      CMAKE_GENERATOR MATCHES "Visual Studio 9" OR
      CMAKE_GENERATOR MATCHES "Visual Studio 10")



