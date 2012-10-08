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

#include <sstream>

#include "./node_nuo_connection.h"
#include "./result.h"
#include <iostream>

node_db_nuodb::Connection::Connection()
    : handle(0) {

    this->port = 48004;
    this->quoteName = '"';
}

node_db_nuodb::Connection::~Connection() {
    this->close();
}

std::string node_db_nuodb::Connection::getSchema() const {
    return this->schema;
}

void node_db_nuodb::Connection::setSchema(const std::string& schema) {
    this->schema = schema;
}

bool node_db_nuodb::Connection::isAlive(bool ping) throw() {
    if (ping && this->alive) {
    }
    return this->alive;
}

void node_db_nuodb::Connection::open() throw(node_db::Exception&) {
    this->close();
    try {
        NuoDB::Connection * connection = NuoDB::Connection::create();
        NuoDB::Properties * props = connection->allocProperties();
        props->putValue("user", this->user.c_str());
        props->putValue("password", this->password.c_str());
        props->putValue("schema", this->schema.c_str());
        //TODO: Missing properties: Hostname and Port

        connection->openDatabase(this->database.c_str(), props);
        handle = reinterpret_cast<uintptr_t>(connection);

        this->alive = true;
    } catch(NuoDB::SQLException & exception) {
        throw node_db::Exception(exception.getText());
    }
}

void node_db_nuodb::Connection::close() {
    if (this->alive) {
        NuoDB::Connection * connection = reinterpret_cast<NuoDB::Connection*>(handle);
        connection->close();
        handle = 0;
    }
    this->alive = false;
}

std::string node_db_nuodb::Connection::escape(const std::string& string) const throw(node_db::Exception&) {
    throw node_db::Exception("This binding does not implement escape()");
}

std::string node_db_nuodb::Connection::version() const {
    if (this->alive) {
        NuoDB::Connection * connection = reinterpret_cast<NuoDB::Connection*>(handle);
        NuoDB::DatabaseMetaData * dmd = connection->getMetaData();
        char const * version = dmd->getDatabaseProductVersion();
        return version;
    }
    return NULL;
}

node_db::Result* node_db_nuodb::Connection::query(const std::string& query) const throw(node_db::Exception&) {
    NuoDB::Connection * connection = reinterpret_cast<NuoDB::Connection*>(handle);
    try {
        NuoDB::PreparedStatement * statement = connection->prepareStatement(query.c_str());
        NuoDB::ResultSet * results = statement->executeQuery();
        node_db_nuodb::Result * result = new node_db_nuodb::Result(results, statement);
        return result;
    } catch(NuoDB::SQLException & ex) {
        throw node_db::Exception(ex.getText());
    }
}
