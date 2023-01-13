#!/bin/python
# -*- coding: UTF-8 -*-

import sys,os,string;
from csv import DictReader

def transliterate(name):
   """
   Автор: LarsKort
   Дата: 16/07/2011; 1:05 GMT-4;
   Не претендую на "хорошесть" словарика. В моем случае и такой пойдет,
   вы всегда сможете добавить свои символы и даже слова. Только
   это нужно делать в обоих списках, иначе будет ошибка.
   """
   # Словарь с заменами
   slovar = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e',
      'ж':'zh','з':'z','и':'i','й':'i','к':'k','л':'l','м':'m','н':'n',
      'о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h',
      'ц':'c','ч':'cz','ш':'sh','щ':'scz','ъ':'','ы':'y','ь':'','э':'e',
      'ю':'u','я':'ja', 'А':'A','Б':'B','В':'V','Г':'G','Д':'D','Е':'E','Ё':'Yo',
      'Ж':'J','З':'Z','И':'I','Й':'Y','К':'K','Л':'L','М':'M','Н':'N',
      'О':'O','П':'P','Р':'R','С':'S','Т':'T','У':'U','Ф':'F','х':'H',
      'Ц':'C','Ч':'Ch','Ш':'Sh','Щ':'Scz','Ъ':'','Ы':'i','Ь':'','Э':'E',
      'Ю':'YU','Я':'JA',',':'','?':'',' ':'_','~':'','!':'','@':'','#':'',
      '$':'','%':'','^':'','&':'','*':'','(':'',')':'','-':'','=':'','+':'',
      ':':'',';':'','<':'','>':'','\'':'','"':'','\\':'','/':'','№':'',
      '[':'',']':'','{':'','}':'','ґ':'','ї':'', 'є':'','Ґ':'g','Ї':'i',
      'Є':'e'}
   # Циклически заменяем все буквы в строке
   for key in slovar:
      name = name.replace(key, slovar[key])
   return name


# print "----- VoipSwitch CSV-Parser for add NDS (Python) -----";

if (len(sys.argv)<2):
   print "   Usage: %s <csv-file>" % (sys.argv[0]);
   sys.exit(2);
else:
   try:
     inifile = open(sys.argv[1], 'r');
   except IOError, err:
     print "   Error opening CSV-file: " + err.strerror
     exit(3);

# print "   CSV-File: " + os.path.basename(sys.argv[1]);

print "prefix;description;voice_rate;from_day;to_day;from_hour;to_hour;grace_period;minimal_time;resolution;rate_multiplier;rate_addition;surcharge_time;surcharge_amount;free_seconds\r";

try:
    with open(sys.argv[1], 'r') as f:
        ln = DictReader(f, ['prefix', 'name', 'price'], # передаем имена полей для словаря
                              delimiter=',',                        # задаем символ-разделитель
                              skipinitialspace=True)                # пропускаем начальные пробелы после символа-разделителя
        for record in ln:       # читаем записи (строки) в *.csv файле
            prefix = "{prefix}".format(**record);
            name = transliterate("{name}".format(**record));
            price = "{price}".format(**record);
            pricends = float(price.replace(',', '.')) * 1.18;
            print '%s;"%s";%f;0;6;0;2400;0;-1;-1;-1;-1;-1;-1.0000;\r' % (prefix, name, pricends);
except Exception as e:
    print(e)
    raise SystemExit(1)

