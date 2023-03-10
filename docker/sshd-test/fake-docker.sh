#!/usr/bin/env bash

if [ $1 = "run" ]; then
   echo acdea168264a08f9aaca0dfc82ff3551418dfd22d02b713142a6843caa2f61bf
   exit 0
fi;

if [ $1 = "stop" ]; then
   echo acdea168264a08f9aaca0dfc82ff3551418dfd22d02b713142a6843caa2f61bf
   exit 0
fi;

if [ $1 = "exec" ]; then
   cat /var/log/alternatives.log
   exit 0
fi;
