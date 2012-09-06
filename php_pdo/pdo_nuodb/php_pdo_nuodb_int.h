/****************************************************************************
 * Copyright (c) 2012, NuoDB, Inc.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of NuoDB, Inc. nor the names of its contributors may
 *       be used to endorse or promote products derived from this software
 *       without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL NUODB, INC. BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA,
 * OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
 * OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 * ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 ****************************************************************************/

#ifndef PHP_PDO_NUODB_INT_H
#define PHP_PDO_NUODB_INT_H

#if PHP_DEBUG && !defined(PHP_WIN32)
#define PDO_DBG_ENABLED 1

#define PDO_DBG_INF(msg) do { if (dbg_skip_trace == FALSE) pdo_nuodb_log(__LINE__, __FILE__, "info", (msg)); } while (0)
#define PDO_DBG_ERR(msg) do { if (dbg_skip_trace == FALSE) pdo_nuodb_log(__LINE__, __FILE__, "error", (msg)); } while (0)
#define PDO_DBG_INF_FMT(...) do { if (dbg_skip_trace == FALSE) pdo_nuodb_log_va(__LINE__, __FILE__, "info", __VA_ARGS__); } while (0)
#define PDO_DBG_ERR_FMT(...) do { if (dbg_skip_trace == FALSE) pdo_nuodb_log_va(__LINE__, __FILE__, "error", __VA_ARGS__); } while (0)
#define PDO_DBG_ENTER(func_name) int dbg_skip_trace = TRUE; dbg_skip_trace = !pdo_nuodb_func_enter(__LINE__, __FILE__, func_name, strlen(func_name));
#define PDO_DBG_RETURN(value)	do { pdo_nuodb_func_leave(__LINE__, __FILE__); return (value); } while (0)
#define PDO_DBG_VOID_RETURN	do { pdo_nuodb_func_leave(__LINE__, __FILE__); return; } while (0)

#else
#define PDO_DBG_ENABLED 0

static inline void PDO_DBG_INF(char *msg) {}
static inline void PDO_DBG_ERR(char *msg) {}
static inline void PDO_DBG_INF_FMT(char *format, ...) {}
static inline void PDO_DBG_ERR_FMT(char *format, ...) {}
static inline void PDO_DBG_ENTER(char *func_name) {}
#define PDO_DBG_RETURN(value)	return (value)
#define PDO_DBG_VOID_RETURN		return;

#endif

extern pdo_driver_t pdo_nuodb_driver;

extern struct pdo_stmt_methods nuodb_stmt_methods;

void _nuodb_error(pdo_dbh_t * dbh, pdo_stmt_t * stmt, char const * file, long line TSRMLS_DC);

enum
{
    PDO_NUODB_ATTR_DATE_FORMAT = PDO_ATTR_DRIVER_SPECIFIC,
    PDO_NUODB_ATTR_TIME_FORMAT,
    PDO_NUODB_ATTR_TIMESTAMP_FORMAT,
};

#endif	/* PHP_PDO_NUODB_INT_H */
