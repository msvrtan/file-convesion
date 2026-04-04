## 2026-04-04T11:33:20+02:00
create pr please

## 2026-04-04T15:13:49+02:00 [gpt-5.2 medium]
please read business and engineering requirements

## 2026-04-04T15:16:38+02:00 [gpt-5.4 high]
README.md should be used for reviewer notes, so create 2-3 sentences long intro to project
## 2026-04-04T15:29:03+02:00 [gpt-5.4 high]
please set up phpstan to be extra strict and use all of the installed extensions\

## 2026-04-04T15:33:18+02:00 [gpt-5.4 high]
- phpstan should cover just src and tests folder\

## 2026-04-04T15:35:29+02:00 [gpt-5.4 high]
please create phpstan baseline file

## 2026-04-04T15:37:41+02:00 [gpt-5.4 high]
please set up cs-fixer config so it runs all psr and symfony standards + strict types need to be declared everywhere


## 2026-04-04T15:40:22+02:00 [gpt-5.4 high]
instead of defining whole project, please run php-cs-fixer over src and tests folders only

## 2026-04-04T15:42:31+02:00 [gpt-5.4 high]
please create Makefile with running cs fixer in fix and dry mode, another for phpstan and one for phpunit

## 2026-04-04T15:44:53+02:00 [gpt-5.4 high]
do we need allow risky since it's in config already?

## 2026-04-04T15:45:48+02:00 [gpt-5.4 high]
also do we need to menion src and tests when it's also in config?

## 2026-04-04T15:46:03+02:00 [gpt-5.4 high]
please move that makefile into api folder

## 2026-04-04T15:48:28+02:00 [gpt-5.4 high]
add to makefile generating jwt keypair

## 2026-04-04T15:50:36+02:00 [gpt-5.4 high]
create pr

