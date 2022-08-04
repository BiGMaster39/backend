<?php
namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\SupportTicketsModel;
use App\Models\SupportCommentsModel;
use App\Models\UsersModel;
use App\Libraries\Uid;
use ReflectionException;

define("LIMIT", 20);

class Support extends PrivateController
{
    private $tickets;
    private $comments;
    private $users;
    private $uid;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->tickets = new SupportTicketsModel();
        $this->comments = new SupportCommentsModel();
        $this->users = new UsersModel();
        $this->uid = new Uid();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get list all tickets for user
     * @param int $offset
     * @param int $userID
     * @return mixed
     */
    public function all(int $offset = 0, int $userID = 0)
    {
        $tickets = $this->tickets
            ->where("user_id", $userID)
            ->orderBy("updated_at", "DESC")
            ->findAll(LIMIT, $offset);
        $count = $this->tickets
            ->where("user_id", $userID)
            ->countAllResults();
        $list = [];
        foreach ($tickets as $ticket) {
            $last_comment = $this->comments
                ->where("ticket_id", $ticket["id"])
                ->select("message,created_at")
                ->orderBy("id", "DESC")
                ->first();
            $list[] = [
                "uid"     => $ticket["uid"],
                "title"   => $ticket["title"],
                "status"  => (int) $ticket["status"],
                "updated" => date('d-m-Y H:i', $ticket['updated_at']),
                "message" => [
                    "comment" => $last_comment["message"],
                    "created" => date('d-m-Y', $last_comment['created_at']),
                ],

            ];
        }
        return $this->respond([
            "list"  => $list,
            "count" => $count
        ], 200);
    }

    /**
     * Get list tickets
     * @param int $sort
     * 0 - inbox
     * 1 - pending
     * 2 - archive
     * @param int $offset
     * @return mixed
     */
    public function list(int $sort = 0, int $offset = 0)
    {
        if (!$sort) {
            $where = ["status" => 0];
        } else if ($sort == 1) {
            $where = ["status" => 1];
        } else {
            $where = ["status" => 2];
        }
        $tickets = $this->tickets
            ->where($where)
            ->orderBy("updated_at", $sort == 0 ? "ASC" : "DESC")
            ->findAll(LIMIT, (int) $offset);
        $count = $this->tickets
            ->where($where)
            ->countAllResults();
        $list = [];
        foreach ($tickets as $ticket) {
            $last_comment = $this->comments
                ->where("ticket_id", $ticket["id"])
                ->select("message,created_at")
                ->orderBy("id", "DESC")
                ->first();
            $list[] = [
                "uid"     => $ticket["uid"],
                "title"   => $ticket["title"],
                "status"  => (int) $ticket["status"],
                "updated" => date('d-m-Y H:i', $ticket['updated_at']),
                "message" => [
                    "comment" => $last_comment["message"],
                    "created" => date('d-m-Y', $last_comment['created_at']),
                ],

            ];
        }
        return $this->respond([
            "list"  => $list,
            "count" => $count
        ], 200);
    }

    /**
     * Get list comments
     * @param string $uid
     * @return mixed
     */
    public function comments(string $uid = "")
    {
        $ticket = $this->tickets
            ->where(["uid" => esc($uid)])
            ->select("id,title,status,uid,user_id")
            ->first();
        if (!$ticket) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_49")
                ],
            ], 400);
        }
        $comments = $this->comments
            ->where("ticket_id", $ticket["id"])
            ->orderBy("id", "ASC")
            ->findAll();
        $user = $this->users
            ->where("id", $ticket["user_id"])
            ->select("id,email")
            ->first();
        $list = [];
        foreach ($comments as $comment) {
            $list[] = [
                "uid"        => $comment["uid"],
                "message"    => $comment["message"],
                "created"    => date('d-m-Y H:i', $comment['created_at']),
                "estimation" => (int) $comment["estimation"],
                "admin"      => !$comment["user_id"],
                "loading"    => false,
            ];
        }
        return $this->respond([
            "list"   => $list,
            "ticket" => [
                "title"  => $ticket["title"],
                "uid"    => $ticket["uid"],
                "status" => (int) $ticket["status"]
            ],
            "user"   => [
                "id"     => $user["id"],
                "email"  => $user["email"]
            ]
        ], 200);
    }

    /**
     * Create new comment
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function create_comment(string $uid = "")
    {
        if (!$this->validate($this->create_comment_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $ticket = $this->tickets
            ->where(["uid" => esc($uid)])
            ->select("id,status")
            ->first();
        if (!$ticket) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_49")
                ],
            ], 400);
        }
        if ($ticket["status"] == 2) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_50")
                ],
            ], 400);
        }
        $comment_uid = $this->uid->create();
        $this->comments->insert([
            "user_id"    => 0,
            "message"    => esc($this->request->getPost("message")),
            "estimation" => 0,
            "uid"        => $comment_uid,
            "ticket_id"  => $ticket["id"]
        ]);
        $this->tickets->update($ticket["id"], [
            "status" => 1
        ]);
        return $this->respond([
            "code"    => 200,
            "uid"     => $comment_uid,
            "created" => date('d-m-Y H:i')
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for create new comment
     * @return array
     */
    private function create_comment_validation_type(): array
    {
        return [
            "message" => ["label" => lang("Fields.field_79"),  "rules" => "required|min_length[2]|max_length[1000]"],
        ];
    }
}