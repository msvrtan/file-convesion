# Developer Offline Test Task

## Problem

Your customer wants to execute a job that takes more than 2 minutes to complete.
The job is about format conversion: your customer will send you a file, and they will get back the converted version of the file. The file data is not known up front, and the size is also not known.

## Execution

Write an API to let your customer ask to execute a long-running job.

Supported file types in input are: **CSV**, **JSON**, **XLSX**, and **ODS**.

Output file formats are: **JSON** and **XML**.

The job does not need to execute a real conversion (can be just a dummy/abstract service for that), and a sleep can be enough.

Please include some tests that will simulate or even fully cover your thought process and application workflow.

## Instructions and Hints

- Use a Git repository, make it public so reviewers can access it
- Use the PHP programming language
- You can use Symfony ecosystem packages
- You can use external packages to do part of the work
- Write the code according to at least the PSR standards
- Prepare a README file to facilitate reading for reviewers
